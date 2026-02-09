<?php

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Abadi;
use App\Models\Alley;
use App\Models\City;
use App\Models\County;
use App\Models\District;
use App\Models\ExperienceField;
use App\Models\Group;
use App\Models\Message;
use App\Models\Neighborhood;
use App\Models\OccupationalField;
use App\Models\Province;
use App\Models\Continent;
use App\Models\Region;
use App\Models\Rural;
use App\Models\Street;
use App\Models\Village;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use App\Http\Controllers\Group\ChatController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('provinces', function(){
    return response()->json([
        'data' => Province::where('status', 1)->get(),
    ]);
});

Route::get('counties/{id}', function($id){
    return response()->json([
        'data' => County::where('province_id', $id)->where('status', 1)->get(),
    ]);
});

Route::get('districts/{id}', function($id){
    return response()->json([
        'data' => District::where('county_id', $id)->where('status', 1)->get(),
    ]);
});

Route::get('cities/{id}', function($id){
    return response()->json([
        'data' => City::where('district_id', $id)->where('status', 1)->get(),
    ]);
});

Route::get('villages/{id}', function($id){
    return response()->json([
        'data' => Village::where('district_id', $id)->where('status', 1)->get(),
    ]);
});

Route::get('abadi/{id}', function($id){
    return response()->json([
        'data' => Abadi::where('village_id', $id)->where('status', 1)->get(),
    ]);
});



Route::get('/locations', function (Request $request) {
    // سطح درخواستی (مثلاً country, province, city, ...) از پارامتر GET
    $level = $request->query('level');
    // شناسه والد (مثلاً continent_id برای سطح country)
    $parentId = $request->query('parent_id');

    // ساختار switch می‌تواند مناسب باشد، اما می‌توانید با هر روشی که راحت‌تر هستید پیاده‌سازی کنید
    $data = [];

    switch ($level) {
        case 'country':
            // همه کشورهایی که continent_id برابر parentId داشته باشند
            $data = Country::where('continent_id', $parentId)->get(['id','name']);
            break;

        case 'province':
            $data = Province::where('country_id', $parentId)->get(['id','name']);
            break;

        case 'county':
            $data = County::where('province_id', $parentId)->get(['id','name']);
            break;

        case 'section':
            $data = District::where('county_id', $parentId)->get(['id','name']);
            break;

        case 'city':
            $cities = \App\Models\City::where('district_id', $parentId)
                ->get(['id', 'name'])
                ->map(function ($item) {
                    return [
                        'id' => 'city_' . $item->id,  // ✅ اینجا نوع city اضافه میشه
                        'name' => $item->name . ' (شهر)',
                        'type' => 'city',
                    ];
                });
        
            // بررسی وجود جدول rurals قبل از استفاده
            $rurals = collect([]);
            if (Schema::hasTable('rurals')) {
                try {
                    $rurals = \App\Models\Rural::where('district_id', $parentId)
                        ->get(['id', 'name'])
                        ->map(function ($item) {
                            return [
                                'id' => 'rural_' . $item->id,  // ✅ اینجا نوع rural اضافه میشه
                                'name' => $item->name . ' (دهستان)',
                                'type' => 'rural',
                            ];
                        });
                } catch (\Exception $e) {
                    // اگر خطا رخ داد، فقط cities را برگردان
                    $rurals = collect([]);
                }
            }
        
            $data = $cities->merge($rurals)->values();
            break;
    
        case 'region':
            // بررسی اینکه parent_id مربوط به city است یا rural
            if (Str::startsWith($parentId, 'city_')) {
                $id = (int) Str::after($parentId, 'city_');  // حذف پیشوند city_
                $data = \App\Models\Region::where('parent_id', $id)->get(['id', 'name']);
            } elseif (Str::startsWith($parentId, 'rural_')) {
                $id = (int) Str::after($parentId, 'rural_');  // حذف پیشوند rural_
                $data = \App\Models\Village::where('rural_id', $id)->get(['id', 'name']);
            } else {
                $data = [];
            }
            break;
            
            
        case 'neighborhood':
            $data = Neighborhood::where('parent_id', $parentId)->get(['id','name']);
            break;

        case 'street':
            $data = Street::where('parent_id', $parentId)->get(['id','name']);
            break;

        case 'alley':
            $data = Alley::where('parent_id', $parentId)->get(['id','name']);
            break;

        default:
            // اگر سطح نامشخص باشد، هیچ داده‌ای برنمی‌گردانیم یا می‌توانید ارور مناسب بدهید
            $data = [];
    }
    
    if($level == 'continent'){
        $data = Continent::all();
    }

    return response()->json($data);
});


Route::post('/add-{type}', function(Request $request, $type) {
    $allowed = ['region','neighborhood','street','alley'];
    if (!in_array($type, $allowed, true)) {
        return response()->json(['message' => 'نوع نامعتبر است'], 422);
    }

    // trim name
    $request->merge(['name' => trim((string)$request->input('name'))]);

$request->validate([
  'name' => 'required|string|max:255',
  'parent_id' => ['required','regex:/^(?:\d+|(?:city|rural|region|neighborhood|street|village)_\d+)$/'],
], [], [
  'name' => 'نام',
  'parent_id' => 'شناسه والد',
]);


    $rawParent = (string) $request->input('parent_id');
    $kind = null;
    $pid  = null;

    if (preg_match('/^(city|rural|region|neighborhood|street|village)_(\d+)$/', $rawParent, $m)) {
        $kind = $m[1];
        $pid  = (int) $m[2];
    } elseif (ctype_digit($rawParent)) {
        $pid  = (int) $rawParent;
    } else {
        return response()->json(['message' => 'قالب parent_id نامعتبر است'], 422);
    }

    $name = $request->input('name');

    // قوانین سازگاری نوع والد با نوع درج‌شونده
    $allowedParentsByType = [
        'region'       => ['city','rural'],        // شهر یا دهستان
        'neighborhood' => ['region','village'],    // منطقه یا روستا
        'street'       => ['neighborhood','village'],
        'alley'        => ['street','village'],
    ];

    if ($kind !== null && !in_array($kind, $allowedParentsByType[$type], true) && !ctype_digit($rawParent)) {
        // اگر parent پیشوند داشت ولی با نوع فعلی سازگار نبود
        return response()->json(['message' => 'نوع والد با موجودیت سازگار نیست'], 422);
    }
    try {
        $location = DB::transaction(function () use ($type, $kind, $pid, $name) {
            switch ($type) {
                case 'region':
                    if ($kind === 'rural') {
                        // ✅ اگر والد دهستان بود، Village بساز (طبق منطق قبلی)
                        // (در صورت نیاز، parent ستون را با اسکیمای خودت تنظیم کن)
                        return Village::create([
                            'name'     => $name,
                            'rural_id' => $pid,   // ⬅ در اسکیمای تو ممکن است 'parent_id' یا 'rural_id' باشد
                            'status'   => 0,
                        ]);
                    } else {
                        // والد شهر است (city_* یا عدد خالص city_id)
                        // ⬅ Region از parent_id استفاده می‌کند (city_id به parent_id تبدیل شده)
                        return Region::create([
                            'name'     => $name,
                            'parent_id' => $pid,
                            'status'   => 0,
                        ]);
                    }

                case 'neighborhood':
                    // والد می‌تواند region_* یا village_* باشد
                    // قبل از ساخت، وجود والد را چک کن (اختیاری اما توصیه می‌شود)

                    return Neighborhood::create([
                        'name'      => $name,
                        'parent_id' => $pid,   // ⬅ اگر ستونت 'region_id' است، همین‌جا تغییر بده
                        'status'    => 0,
                    ]);

                case 'street':
                    if ($kind === 'neighborhood' || $kind === null) {
                        if (!Neighborhood::where('id', $pid)->exists()) {
                            throw new \RuntimeException('محله والد یافت نشد.');
                        }
                    } elseif ($kind === 'village') {
                        if (!Village::where('id', $pid)->exists()) {
                            throw new \RuntimeException('روستای والد یافت نشد.');
                        }
                    }
                    return Street::create([
                        'name'      => $name,
                        'parent_id' => $pid,   // ⬅ در صورت تفاوت اسکیمای جدول streets اینجا را تغییر بده
                        'status'    => 0,
                    ]);

                case 'alley':
                    if ($kind === 'street' || $kind === null) {
                        if (!Street::where('id', $pid)->exists()) {
                            throw new \RuntimeException('خیابان والد یافت نشد.');
                        }
                    } elseif ($kind === 'village') {
                        if (!Village::where('id', $pid)->exists()) {
                            throw new \RuntimeException('روستای والد یافت نشد.');
                        }
                    }
                    return Alley::create([
                        'name'      => $name,
                        'parent_id' => $pid,   // ⬅ در صورت تفاوت اسکیمای جدول alleys اینجا را تغییر بده
                        'status'    => 0,
                    ]);
            }
        });

        return response()->json(
            ['id' => $location->id, 'name' => $location->name],
            201
        );
    } catch (\Throwable $e) {
        $msg = $e->getMessage() ?: 'خطا در ثبت مکان.';
        return response()->json(['message' => $msg], 422);
    }
});



// routes/api.php
Route::get('/occupational-fields/{id}/children', function($id){
    $children = OccupationalField::where('parent_id', $id)->get();
    return response()->json($children);
});

Route::post('/occupational-fields', function(Request $request){
    $request->validate([
        'name' => 'required|string|max:255',
        'parent_id' => 'nullable|exists:occupational_fields,id',
    ]);

    $field = OccupationalField::create([
        'name' => $request->name,
        'parent_id' => $request->parent_id
    ]);

    return response()->json($field);
});

Route::post('/experience-fields', function(Request $request){
    $request->validate([
        'name' => 'required|string|max:255',
        'parent_id' => 'nullable|exists:experience_fields,id'
    ]);

    $field = \App\Models\ExperienceField::create([
        'name' => $request->name,
        'parent_id' => $request->parent_id
    ]);

    return response()->json($field);
});

Route::get('/experience-fields/{id}/children', function($id) {
    $children = ExperienceField::where('parent_id', $id)->get();
    return response()->json($children);
});



Route::get('/locations/{level}', function($level,Request $request){
    $map = [
        'continents'    => ['table' => 'continents',    'column' => null],
        'countries'     => ['table' => 'countries',     'column' => 'continent_id'],
        'provinces'     => ['table' => 'provinces',     'column' => 'country_id'],
        'counties'      => ['table' => 'counties',      'column' => 'province_id'],
        'districts'     => ['table' => 'districts',     'column' => 'county_id'],
        'cities'        => ['table' => 'cities',        'column' => 'district_id'],
        'regions'       => ['table' => 'regions',       'column' => 'city_id'],
        'neighborhoods' => ['table' => 'neighborhoods', 'column' => 'region_id'],
        'streets'       => ['table' => 'streets',       'column' => 'neighborhood_id'],
        'alleys'        => ['table' => 'alleies',       'column' => 'street_id'],
    ];

    if (!isset($map[$level])) {
        return response()->json(['error' => 'Invalid level'], 400);
    }

    $config = $map[$level];

    $query = DB::table($config['table'])->select('id', 'name');

    if ($config['column'] && $request->has('parent_id')) {
        $query->where($config['column'], $request->input('parent_id'));
    }

    return response()->json($query->get());
});

/*
|--------------------------------------------------------------------------
| نجم‌هدا API Routes
|--------------------------------------------------------------------------
|
| مسیرهای API برای نجم‌هدا - نرم‌افزار جامع مدیریت هوشمند
|
*/

use App\Http\Controllers\API\NajmHodaController;
use App\Http\Controllers\Api\NajmHodaEscalationController;

// مسیرهای عمومی نجم‌هدا (بدون احراز هویت)
Route::prefix('najm-hoda')->group(function () {
    Route::get('welcome', [NajmHodaController::class, 'welcome']);
    // endpoint for external Najm Hoda service to escalate conversations into tickets
    // rate-limited to avoid abuse
    Route::post('escalate', [NajmHodaEscalationController::class, 'escalate'])->middleware('throttle:30,1');
});

// Email Webhook (Mailgun)
Route::prefix('email')->group(function () {
    Route::post('webhook', [\App\Http\Controllers\API\EmailWebhookController::class, 'webhook']);
});

// مسیرهای نیازمند احراز هویت
Route::middleware('auth:sanctum')->prefix('najm-hoda')->group(function () {
    // چت با نجم‌هدا
    Route::post('chat', [NajmHodaController::class, 'chat']);
    
    // مدیریت مکالمات
    Route::get('conversations', [NajmHodaController::class, 'listConversations']);
    Route::get('conversations/{id}', [NajmHodaController::class, 'getConversation']);
    Route::delete('conversations/{id}', [NajmHodaController::class, 'deleteConversation']);
    Route::put('conversations/{id}/archive', [NajmHodaController::class, 'archiveConversation']);
    
    // بازخورد
    Route::post('feedback', [NajmHodaController::class, 'submitFeedback']);
    
    // آمار (فقط ادمین)
    Route::get('stats', [NajmHodaController::class, 'getStats'])->middleware('admin');
});

// مسیرهای API برای تیکت‌های پشتیبانی
Route::middleware('auth:sanctum')->prefix('tickets')->group(function () {
    Route::get('/', [\App\Http\Controllers\API\TicketController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\API\TicketController::class, 'store']);
    Route::get('stats', [\App\Http\Controllers\API\TicketController::class, 'stats']);
    Route::get('{id}', [\App\Http\Controllers\API\TicketController::class, 'show']);
    Route::put('{id}', [\App\Http\Controllers\API\TicketController::class, 'update']);
    Route::put('{id}/close', [\App\Http\Controllers\API\TicketController::class, 'close']);
    Route::post('{id}/comments', [\App\Http\Controllers\API\TicketController::class, 'addComment']);
    Route::get('{id}/attachments/{attachment_id}/download', [\App\Http\Controllers\API\TicketController::class, 'downloadAttachment']);
});

// مسیر Webhook ایمیل (بدون احراز هویت، با signature verification)
Route::post('email/webhook', [\App\Http\Controllers\API\EmailWebhookController::class, 'webhook']);

// include NajmBahar routes (module)
if (file_exists(base_path('routes/najm-bahar.php'))) {
    require base_path('routes/najm-bahar.php');
}

