<?php

use App\Http\Controllers\Admin\ActivateController;
use App\Http\Controllers\Admin\AddressController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Profile\InvitationController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExperienceController;
use App\Http\Controllers\Admin\GroupController as AdminGroupController;
use App\Http\Controllers\Admin\GroupManagementController;
use App\Http\Controllers\Admin\GroupSettingController;
use App\Http\Controllers\Admin\InvitationCodeController;
use App\Http\Controllers\Admin\RuleController;
use App\Http\Controllers\Admin\NajmBaharController;
use App\Http\Controllers\Admin\NajmBaharFeeController;
use App\Http\Controllers\Admin\NajmBaharSettingsController;
use App\Http\Controllers\Admin\WelcomePageController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\NajmHodaController as AdminNajmHodaController;
use App\Http\Controllers\Admin\FaqQuestionController as AdminFaqQuestionController;
use App\Http\Controllers\Admin\EmailController;
use App\Http\Controllers\Admin\SystemEmailController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Group\ElectionController;
use App\Http\Controllers\Group\GroupController;
use App\Http\Controllers\Group\ChatController;
use App\Http\Controllers\Group\MessageController;
use App\Http\Controllers\Group\CommentController;
use App\Http\Controllers\Group\BlogController;
use App\Http\Controllers\Group\PollController;
use App\Http\Controllers\Group\ReactionController;
use App\Modules\Stock\Controllers\StockController;
use App\Modules\Stock\Controllers\AuctionController;
use App\Modules\Stock\Controllers\BidController;
use App\Modules\Blog\Controllers\BlogController as ModuleBlogController;
use App\Modules\Blog\Controllers\AdminBlogController;
use App\Http\Controllers\LocaleController;

use App\Http\Controllers\Auth\Register\StartController;
use App\Http\Controllers\Auth\Register\Step1Controller;
use App\Http\Controllers\Auth\Register\Step2Controller;
use App\Http\Controllers\Auth\Register\Step3Controller;
use App\Http\Controllers\Profile\HistoryController;
use App\Http\Controllers\Profile\TermController;
use App\Http\Controllers\NajmBaharMembershipFeeController;
use App\Http\Controllers\ReputationConversionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Controllers\ChatRequestController;
use App\Http\Controllers\PrivateChatController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\MessageReactionController;
use App\Http\Controllers\PrivateChatReportController;
use App\Http\Middleware\UpdateLastSeenOnLogout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\ReportedMessageController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\ContentManagementController;
use App\Models\Blog;
use App\Models\Region;
use App\Models\Rural;
use App\Models\Street;
use App\Models\Village;
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

/*
|--------------------------------------------------------------------------
| Language Routes
|--------------------------------------------------------------------------
*/
Route::get('/lang/{locale}', [LocaleController::class, 'change'])->name('locale.change');
Route::get('/lang/current', [LocaleController::class, 'current'])->name('locale.current');

/*
|--------------------------------------------------------------------------
| Static pages
|--------------------------------------------------------------------------
*/
date_default_timezone_set("Asia/tehran");

Route::view('/terms', 'terms')->name('terms');
Route::post('/terms', [TermController::class, 'store'])->name('terms.store');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.process');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware(UpdateLastSeenOnLogout::class);

// درخواست ارسال OTP
Route::get('/forgot-password', [LoginController::class, 'forgotView'])->name('password.reset.view');
Route::post('/forgot-password', [LoginController::class, 'forgot'])->name('password.reset.send');

// ریست پسورد با OTP
Route::get('/reset-password', [LoginController::class, 'resetView'])->name('password.reset.viewForm');;
Route::post('/reset-password', [LoginController::class, 'reset'])->name('password.reset');


/*
|--------------------------------------------------------------------------
| Registration - Multi-Step
|--------------------------------------------------------------------------
*/

// نمایش خوش‌آمدگویی و شروع ثبت‌نام
Route::get('/', [StartController::class, 'showWelcome'])->name('welcome');
Route::post('/register/accept', [StartController::class, 'processAgreement'])->name('register.accept');
Route::get('/register', [StartController::class, 'showRegisterForm'])->name('register.form');
Route::post('/register', [StartController::class, 'processRegister'])->name('register.process');

// مراحل ثبت‌نام که نیاز به احراز هویت و تایید ایمیل دارند
Route::middleware(EnsureEmailIsVerified::class)->group(function () {
    // مرحله ۱: اطلاعات هویتی
    Route::get('/register/step1', [Step1Controller::class, 'show'])->name('register.step1');
    Route::post('/register/step1/validate', [Step1Controller::class, 'validateData'])->name('register.step1.validate');
    Route::post('/register/step1', [Step1Controller::class, 'process'])->name('register.step1.process');

    // مرحله ۲: انتخاب تخصص‌هاpa
    Route::get('/register/step2', [Step2Controller::class, 'show'])->name('register.step2');
    Route::post('/register/step2', [Step2Controller::class, 'process'])->name('register.step2.process');

    // مرحله ۳: انتخاب مکان
    Route::get('/register/step3', [Step3Controller::class, 'show'])->name('register.step3');
    Route::post('/register/step3', [Step3Controller::class, 'process'])->name('register.step3.process');
});

// Admin Support Chat routes
Route::middleware([\App\Http\Middleware\AdminMiddleware::class])->prefix('admin/support-chat')->name('admin.support-chat.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\SupportChatController::class, 'index'])->name('index');
    Route::get('/{chat}', [\App\Http\Controllers\Admin\SupportChatController::class, 'show'])->name('show');
    Route::post('/{chat}/message', [\App\Http\Controllers\Admin\SupportChatController::class, 'sendMessage'])->name('message');
    Route::post('/{chat}/assign', [\App\Http\Controllers\Admin\SupportChatController::class, 'assign'])->name('assign');
    Route::post('/{chat}/convert-to-ticket', [\App\Http\Controllers\Admin\SupportChatController::class, 'convertToTicket'])->name('convert-to-ticket');
    Route::post('/{chat}/close', [\App\Http\Controllers\Admin\SupportChatController::class, 'close'])->name('close');
    Route::post('/auto-assign', [\App\Http\Controllers\Admin\SupportChatController::class, 'autoAssign'])->name('auto-assign');
});

// AJAX helpers (برای فیلدهای مکان و تخصص)
Route::post('/get-children', [Step2Controller::class, 'getChildren'])->name('get.children');
Route::post('/get-field-info', [Step2Controller::class, 'getFieldInfo']);
Route::post('/add-field', [Step2Controller::class, 'addField']);

//get invation code
Route::get('/invation-code', [InvitationController::class, 'get'])->name('invite');
Route::post('/invation-code', [InvitationController::class, 'store'])
    ->middleware('throttle:3,60') // حداکثر 3 درخواست در 60 دقیقه
    ->name('invite.store');

//google login
Route::get('/auth/google', [GoogleController::class, 'redirectToGoogle'])->name('google.login');
Route::get('/auth/google/callback', [GoogleController::class, 'handleGoogleCallback']);

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

    // لیست گروه‌ها و نمایش جزئیات
    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    
    // Test Unified Layout - صفحه تست Layout یکپارچه
    Route::get('/test-unified-layout', function() {
        return view('test-unified-layout');
    })->name('test.unified.layout');

Route::middleware(Authenticate::class)->group(function () {

    // Home
    Route::get('/home', [HomeController::class, 'index'])->name('home');

    // Group Najm Bahar sub-accounts (legal entities)
    Route::get('/groups/{group}/najm-bahar/sub-accounts', [\App\Http\Controllers\NajmBaharSubAccountController::class, 'indexForGroup'])->name('groups.najm-bahar.sub-accounts.index');
    Route::get('/groups/{group}/najm-bahar/sub-accounts/create', [\App\Http\Controllers\NajmBaharSubAccountController::class, 'createForGroup'])->name('groups.najm-bahar.sub-accounts.create');
    Route::post('/groups/{group}/najm-bahar/sub-accounts', [\App\Http\Controllers\NajmBaharSubAccountController::class, 'storeForGroup'])->name('groups.najm-bahar.sub-accounts.store');
    Route::get('/groups/{group}/najm-bahar/sub-accounts/{subAccount}', [\App\Http\Controllers\NajmBaharSubAccountController::class, 'showForGroup'])->name('groups.najm-bahar.sub-accounts.show');
    Route::put('/groups/{group}/najm-bahar/sub-accounts/{subAccount}', [\App\Http\Controllers\NajmBaharSubAccountController::class, 'updateForGroup'])->name('groups.najm-bahar.sub-accounts.update');
    Route::post('/groups/{group}/najm-bahar/sub-accounts/{subAccount}/transfer-to', [\App\Http\Controllers\NajmBaharSubAccountController::class, 'transferToForGroup'])->name('groups.najm-bahar.sub-accounts.transfer-to');
    Route::post('/groups/{group}/najm-bahar/sub-accounts/{subAccount}/transfer-from', [\App\Http\Controllers\NajmBaharSubAccountController::class, 'transferFromForGroup'])->name('groups.najm-bahar.sub-accounts.transfer-from');
    Route::post('/groups/{group}/najm-bahar/sub-accounts/{subAccount}/close', [\App\Http\Controllers\NajmBaharSubAccountController::class, 'closeWithTransferForGroup'])->name('groups.najm-bahar.sub-accounts.close');
    Route::post('/groups/{group}/najm-bahar/sub-accounts/{subAccount}/deactivate', [\App\Http\Controllers\NajmBaharSubAccountController::class, 'deactivateForGroup'])->name('groups.najm-bahar.sub-accounts.deactivate');
    Route::post('/groups/{group}/najm-bahar/sub-accounts/{subAccount}/activate', [\App\Http\Controllers\NajmBaharSubAccountController::class, 'activateForGroup'])->name('groups.najm-bahar.sub-accounts.activate');

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread');
    Route::get('/notifications/latest', [NotificationController::class, 'latest'])->name('notifications.latest');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.delete');
    Route::delete('/notifications/clear-read', [NotificationController::class, 'deleteAllRead'])->name('notifications.clearRead');
    
    // Notification Settings
    Route::get('/notifications/settings', [\App\Http\Controllers\NotificationSettingsController::class, 'index'])->name('notifications.settings');
    Route::put('/notifications/settings', [\App\Http\Controllers\NotificationSettingsController::class, 'update'])->name('notifications.settings.update');

    // Profile
    Route::get('/profile', [ProfileController::class, 'showProfile'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'editModifiable'])->name('profile.edit');
    Route::delete('/profile/document/{index}', [ProfileController::class, 'deleteDocument'])->name('profile.document.delete');

    Route::get('/profile/edit-oc', function(){
        
        // دریافت لیست‌های اولیه جهت انتخاب‌های چندگانه
        $occupationalFields = \App\Models\OccupationalField::whereNull(columns: 'parent_id')->get();
        $experienceFields   = \App\Models\ExperienceField::whereNull('parent_id')->get();
                $user = auth()->user();

        return view('profile.edit-oc', compact('occupationalFields', 'experienceFields', 'user'));
        
    })->name('profile.edit-oc');
    Route::get('/profile/invation-code-generate', [ProfileController::class, 'generateInvationCode'])->name('profile.generate-code');

    Route::put('/profile/update/general', [ProfileController::class, 'updateGeneral'])->name('profile.update.general');
    Route::put('/profile/update/password', [ProfileController::class, 'updatePassword'])->name('profile.update.password');
    Route::put('/profile/update/social-network', [ProfileController::class, 'updateSocialNetworks'])->name('profile.update.social-network');
    Route::put('/profile/update/experience', [ProfileController::class, 'updateExperience'])->name('profile.update.experience');
    Route::put('/profile/update/address', [ProfileController::class, 'updateAddress'])->name('profile.update.address');

    // Debug route to check profile completion status
    Route::get('/debug/profile-status', function() {
        $user = auth()->user();
        if (!$user) {
            return 'Not logged in';
        }
        
        $hasExperience = \App\Models\UserExperience::where('user_id', $user->id)->exists();
        $hasAddress = \App\Models\Address::where('user_id', $user->id)->exists();
        $step1Complete = ($user->first_name && $user->last_name && $user->gender && $user->national_id && $user->phone);
        $isProfileComplete = $step1Complete && $hasExperience && $hasAddress;
        
        return response()->json([
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_status' => $user->status,
            'step1_complete' => $step1Complete,
            'step1_details' => [
                'first_name' => $user->first_name ? '✓' : '✗',
                'last_name' => $user->last_name ? '✓' : '✗',
                'gender' => $user->gender ? '✓' : '✗',
                'national_id' => $user->national_id ? '✓' : '✗',
                'phone' => $user->phone ? '✓' : '✗',
            ],
            'has_experience' => $hasExperience,
            'has_address' => $hasAddress,
            'is_profile_complete' => $isProfileComplete,
        ]);
    })->name('debug.profile-status');

    Route::post('/profile/send-invitation', [ProfileController::class, 'sendInvitation'])->name('profile.send.invitation');
    Route::match(['GET', 'POST'], '/profile/show-info', [ProfileController::class, 'showInfo'])->name(name: 'profile.show.info');

    Route::get('/profile/accept-candidate/{type}', [ProfileController::class, 'acceptCandidate'])->name('profile.accept.candidate');

    Route::get('/profile/join-group/{type}', [ProfileController::class, 'profileJoinGroup'])->name('profile.join.group');
    
    // User Tickets
    Route::prefix('tickets')->name('user.tickets.')->group(function () {
        Route::get('/', [\App\Http\Controllers\UserTicketController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\UserTicketController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\UserTicketController::class, 'store'])->name('store');
        Route::get('/{ticket}', [\App\Http\Controllers\UserTicketController::class, 'show'])->name('show');
        Route::post('/{ticket}/comment', [\App\Http\Controllers\UserTicketController::class, 'comment'])->name('comment');
    });
    
    // User Support Chat
    Route::prefix('support-chat')->name('user.support-chat.')->group(function () {
        Route::get('/', [\App\Http\Controllers\User\SupportChatController::class, 'index'])->name('index');
        Route::post('/{chat}/message', [\App\Http\Controllers\User\SupportChatController::class, 'sendMessage'])->name('message');
        Route::get('/{chat}/messages', [\App\Http\Controllers\User\SupportChatController::class, 'getMessages'])->name('messages');
        Route::post('/{chat}/convert-to-ticket', [\App\Http\Controllers\User\SupportChatController::class, 'convertToTicket'])->name('convert-to-ticket');
        Route::post('/{chat}/close', [\App\Http\Controllers\User\SupportChatController::class, 'close'])->name('close');
    });
    
    // Knowledge Base (Public)
    Route::prefix('support/knowledge-base')->name('support.kb.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Support\KbController::class, 'index'])->name('index');
        Route::get('/suggest', [\App\Http\Controllers\Support\KbController::class, 'suggest'])->name('suggest');
        Route::get('/{slug}', [\App\Http\Controllers\Support\KbController::class, 'show'])->name('show');
    });
    
    
    /*
    |--------------------------------------------------------------------------
    | Group & Chat Routes
    |--------------------------------------------------------------------------
    */
    

    // لیست گروه‌ها و نمایش جزئیات
    // Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::get('/groups/{group}/logout', [GroupController::class, 'logout'])->name('groups.logout');
    Route::get('/groups/{group}/relogout', [GroupController::class, 'relogout'])->name('groups.relogout');
    Route::get('/groups/{group}/open', [GroupController::class, 'open'])->name('groups.open');
    Route::put('/groups/{group}', [GroupController::class, 'update'])->name('groups.update');
    Route::get('/groups/{group}/members', [GroupController::class, 'getMembers'])->name('groups.members');
    Route::post('/groups/{group}/users/{user}/toggle-role', [GroupController::class, 'toggleUserRole'])->name('groups.members.toggle-role');
    Route::get('/groups/{group}/stats', [GroupController::class, 'getStats'])->name('groups.stats');
    Route::get('/groups/{group}/najm-hoda/settings', [GroupController::class, 'najmHodaSettings'])->name('groups.najm-hoda.settings');
    Route::put('/groups/{group}/najm-hoda/settings', [GroupController::class, 'updateNajmHodaSettings'])->name('groups.najm-hoda.settings.update');
    Route::get('/groups/{group}/najm-hoda/action-items', [GroupController::class, 'najmHodaActionItems'])->name('groups.najm-hoda.action-items');
    Route::put('/groups/{group}/najm-hoda/action-items/{actionItem}', [GroupController::class, 'updateNajmHodaActionItem'])->name('groups.najm-hoda.action-items.update');
    Route::get('/groups/{group}/najm-hoda/panel', [GroupController::class, 'najmHodaPanel'])->name('groups.najm-hoda.panel');
    Route::get('/groups/{group}/najm-hoda/guide', [GroupController::class, 'najmHodaGuide'])->name('groups.najm-hoda.guide');
    
    // مدیریت گزارش‌های پیام توسط مدیران گروه - فقط برای مدیران
    Route::get('/groups/{group}/reports', [\App\Http\Controllers\Group\ReportController::class, 'index'])->name('groups.reports');
    Route::get('/groups/{group}/reports/{report}', [\App\Http\Controllers\Group\ReportController::class, 'show'])->name('groups.reports.show');
    Route::post('/groups/{group}/reports/{report}/review', [\App\Http\Controllers\Group\ReportController::class, 'review'])->name('groups.reports.review');

    // تنظیمات کاربر در گروه
    Route::get('/groups/{group}/settings', [\App\Http\Controllers\Group\GroupSettingController::class, 'getSettings'])->name('groups.settings');
    Route::post('/groups/{group}/toggle-mute', [\App\Http\Controllers\Group\GroupSettingController::class, 'toggleMute'])->name('groups.toggle-mute');
    Route::post('/groups/{group}/toggle-archive', [\App\Http\Controllers\Group\GroupSettingController::class, 'toggleArchive'])->name('groups.toggle-archive');

    // چت گروهی
    Route::get('/groups/chat/{group}', [ChatController::class, 'chat'])->name('groups.chat');
    Route::get('/api/groups/{group}/messages', [ChatController::class, 'chatAPI']);
    Route::get('/messages/{message}/voice', [MessageController::class, 'voice'])->name('groups.messages.voice');
    Route::get('/api/groups/{group}/posts/feed', [ChatController::class, 'postsFeed'])->name('groups.posts.feed');
    Route::post('/api/groups/{group}/posts/reconcile', [ChatController::class, 'postsReconcile'])->name('groups.posts.reconcile');
    
    Route::get('/search-users', [GroupController::class, 'searchUsers']);
    Route::post('/add-users-to-group', [GroupController::class, 'addUsersToGroup']);
    

    //delegation
    Route::get('/groups/delegation/{poll}/{expert}', [ChatController::class, 'delegation'])->name('groups.delegation');

    //show profile
    Route::get('/profile-member/{user}', [ProfileController::class, 'showProfileMember'])->name('profile.member.show');

    // ارسال پیام در گروه
    Route::post('/messages/send', [MessageController::class, 'store'])->middleware('group.chat.timing')->name('groups.messages.store');
    Route::post('/messages/{message}/edit', [MessageController::class, 'edit'])->name('groups.messages.edit');
    Route::post('/messages/{message}/delete', [MessageController::class, 'delete'])->name('groups.messages.delete');
    Route::post('/messages/{message}/mark-read', [MessageController::class, 'markAsRead'])->name('messages.mark-read');
    Route::get('/messages/{message}/thread', [MessageController::class, 'getThreadReplies'])->name('messages.thread');
    Route::post('/messages/update-last-read/{group}', [MessageController::class, 'updateLastReadMessage'])->name('groups.messages.updateLastRead');
    Route::post('/groups/{group}/typing', [MessageController::class, 'typing'])->name('groups.messages.typing');
    Route::post('/messages/{message}/reaction', [MessageController::class, 'toggleReaction'])->name('messages.reaction');
    Route::post('/groups/messages/{message}/pin', [MessageController::class, 'pin'])->name('messages.pin');
    Route::post('/groups/messages/{message}/unpin', [MessageController::class, 'unpin'])->name('messages.unpin');
    Route::post('/groups/messages/{message}/report', [MessageController::class, 'report'])->name('messages.report');
    Route::get('/groups/{group}/mention-users', [MessageController::class, 'searchUsersForMention'])->name('groups.messages.mention-users');
    
        Route::get('/groups/{group}/search', [MessageController::class, 'search'])
             ->name('groups.search');

    Route::get('/change-user-role/{user_id}/{group_id}', function($user_id, $group_id){
        $user = \App\Models\User::find($user_id);

        $groupUser = \App\Models\GroupUser::where('user_id', $user_id)->where('group_id', $group_id)->first();

        if($groupUser->role == 0){
            $groupUser->role = 5;
            $groupUser->save();
            return back()->with('success', 'نقش کاربر ' . $user->fullName() . ' از ناظر به فعال ۲ تغییر پیدا کرد');
        }elseif($groupUser->role == 5){
            $groupUser->role = 0;
            $groupUser->save();
            return back()->with('success', 'نقش کاربر ' . $user->fullName() . ' از فعال ۲ به ناظر تغییر پیدا کرد');
        }else{
            return back()->with('success', 'نقش غیرمجاز است');
        }
    })->name('change-user-role');

    // کامنت‌ها
    Route::post('/comment/send', [CommentController::class, 'store'])->name('groups.comment.store');
        Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    
    Route::get('/groups/comment/{blog}', [CommentController::class, 'comment'])->name('groups.comment');
    Route::get('/api/comments/{blog}/messages', [CommentController::class, 'commentAPI']);
    Route::get('/api/comments/{blog}/feed', [CommentController::class, 'commentsFeed']);
    // ارسال پست
    Route::post('/blog/send/{group}', [BlogController::class, 'store'])->middleware('group.chat.timing')->name('groups.blog.store');
    Route::delete('/blog/{blog}', [BlogController::class, 'destroy'])->name('groups.blog.destroy');
    Route::put('/blog/{blog}', [BlogController::class, 'update'])->name('groups.blog.update');
    Route::post('/blog/{blog}/mark-read', [BlogController::class, 'markAsRead'])->name('blogs.mark-read');

    // نظرسنجی و رأی‌گیری
    Route::post('/poll/send/{group}', [PollController::class, 'store'])->middleware('group.chat.timing')->name('groups.poll.store');
    Route::put('/poll/{group}/poll/{poll}', [PollController::class, 'update'])->name('groups.poll.update');
    Route::post('/poll/{group}/delete/{poll}', [PollController::class, 'delete'])->name('groups.poll.delete');

    Route::post('/polls/{poll}/vote', [PollController::class, 'vote'])->middleware('group.chat.timing')->name('poll.vote');
    Route::post('/poll/{poll}/mark-read', [PollController::class, 'markAsRead'])->name('polls.mark-read');

    // ری‌اکت‌ها
    Route::post('/blogs/{blog}/react', [ReactionController::class, 'blogReact'])->name('blogs.react');
    Route::post('/comments/{comment}/react', [ReactionController::class, 'commentReact'])->name('comments.react');
    Route::post('/election/{group}/vote', [ElectionController::class, 'submitVote'])->name('vote');
    Route::post('/finish-election/{election}', [ElectionController::class, 'finishElection'])->name('finish.election');

        /*
    |--------------------------------------------------------------------------
    | History Routes
    |--------------------------------------------------------------------------
    */
    Route::get('history', [HistoryController::class, 'index'])->name('history.index');
    Route::get('history/election', [HistoryController::class, 'election'])->name('history.election');
    Route::get('history/poll', [HistoryController::class, 'poll'])->name('history.poll');

    
    Route::get('my-invation-code', function(){
        return view('my-invation-code');
    
    })->name('my-invation-code');
    
    // سهام و حراج - کاربر
    Route::get('auctions', [\App\Modules\Stock\Controllers\AuctionController::class, 'index'])->name('auction.index');
    Route::get('auctions/{auction}', [\App\Modules\Stock\Controllers\AuctionController::class, 'show'])->name('auction.show');
    Route::post('auctions/{auction}/bid', [\App\Modules\Stock\Controllers\AuctionController::class, 'placeBid'])->name('auction.bid');

    // دفتر سهام - صفحه اصلی بازار (داشبورد)
    Route::get('stock-book', [\App\Modules\Stock\Controllers\StockController::class, 'book'])->name('stock.book');

    // Bid edit/update/cancel for authenticated users
        Route::get('bids/{bid}/edit', [\App\Modules\Stock\Controllers\BidController::class, 'edit'])->name('bid.edit');
        Route::put('bids/{bid}', [\App\Modules\Stock\Controllers\BidController::class, 'update'])->name('bid.update');
        Route::delete('bids/{bid}', [\App\Modules\Stock\Controllers\BidController::class, 'destroy'])->name('bid.destroy');
    
    Route::get('wallet', [\App\Modules\Stock\Controllers\WalletController::class, 'index'])->name('wallet.index');
    Route::get('holdings', [\App\Modules\Stock\Controllers\HoldingController::class, 'index'])->name('holding.index');
    Route::get('holdings/{stock}', [\App\Modules\Stock\Controllers\HoldingController::class, 'show'])->name('holding.show');
    
    // Dashboard نجم بهار
    Route::get('najm-bahar/dashboard', [\App\Http\Controllers\NajmBaharController::class, 'dashboard'])->name('najm-bahar.dashboard');
    Route::get('najm-bahar/wallet', [\App\Http\Controllers\NajmBaharController::class, 'wallet'])->name('najm-bahar.wallet');
    Route::get('najm-bahar/transfer', [\App\Http\Controllers\NajmBaharTransferController::class, 'create'])->name('najm-bahar.transfer');
    Route::post('najm-bahar/transfer', [\App\Http\Controllers\NajmBaharTransferController::class, 'store'])->name('najm-bahar.transfer.store');
    Route::get('najm-bahar/transfer/preview', [\App\Http\Controllers\NajmBaharTransferController::class, 'previewTarget'])->name('najm-bahar.transfer.preview');
    
    // حق عضویت نجم بهار
    Route::get('najm-bahar/membership-fee/info', [NajmBaharMembershipFeeController::class, 'getInfo'])->name('najm-bahar.membership-fee.info');
    Route::post('najm-bahar/membership-fee/pay', [NajmBaharMembershipFeeController::class, 'pay'])->name('najm-bahar.membership-fee.pay');
    
    // تبدیل امتیاز به پول اکتیو
    Route::get('reputation/conversion/info', [ReputationConversionController::class, 'getInfo'])->name('reputation.conversion.info');
    Route::post('reputation/conversion/convert', [ReputationConversionController::class, 'convert'])->name('reputation.conversion.convert');
    
    // تنظیمات اعلان‌های نجم بهار (redirect به تنظیمات اصلی - سیستم موقت حذف شد)
    Route::get('najm-bahar/notification-settings', function () {
        return redirect()->route('notifications.settings');
    })->name('najm-bahar.notification-settings');
    Route::put('najm-bahar/notification-settings', function () {
        return redirect()->route('notifications.settings');
    })->name('najm-bahar.notification-settings.update');
    
    // گزارش‌های مالی نجم بهار
    Route::get('najm-bahar/reports', [\App\Http\Controllers\NajmBaharReportController::class, 'index'])->name('najm-bahar.reports');
    Route::get('najm-bahar/reports/export-pdf', [\App\Http\Controllers\NajmBaharReportController::class, 'exportPdf'])->name('najm-bahar.reports.export-pdf');
    Route::get('najm-bahar/reports/export-excel', [\App\Http\Controllers\NajmBaharReportController::class, 'exportExcel'])->name('najm-bahar.reports.export-excel');

    // گزارش‌های مالی گروه نجم بهار
    Route::get('groups/{group}/najm-bahar/reports', [\App\Http\Controllers\NajmBaharReportController::class, 'indexForGroup'])->name('groups.najm-bahar.reports');
    Route::get('groups/{group}/najm-bahar/reports/export-pdf', [\App\Http\Controllers\NajmBaharReportController::class, 'exportPdfForGroup'])->name('groups.najm-bahar.reports.export-pdf');
    Route::get('groups/{group}/najm-bahar/reports/export-excel', [\App\Http\Controllers\NajmBaharReportController::class, 'exportExcelForGroup'])->name('groups.najm-bahar.reports.export-excel');

    // داشبورد و کیف پول نجم بهار گروه
    Route::get('groups/{group}/najm-bahar/dashboard', [\App\Http\Controllers\NajmBaharGroupController::class, 'dashboard'])->name('groups.najm-bahar.dashboard');
    Route::get('groups/{group}/najm-bahar/wallet', [\App\Http\Controllers\NajmBaharGroupController::class, 'wallet'])->name('groups.najm-bahar.wallet');
    Route::get('groups/{group}/najm-bahar/transfer', [\App\Http\Controllers\NajmBaharTransferController::class, 'createForGroup'])->name('groups.najm-bahar.transfer');
    Route::post('groups/{group}/najm-bahar/transfer', [\App\Http\Controllers\NajmBaharTransferController::class, 'storeForGroup'])->name('groups.najm-bahar.transfer.store');
    Route::get('groups/{group}/najm-bahar/transfer/preview', [\App\Http\Controllers\NajmBaharTransferController::class, 'previewTargetForGroup'])->name('groups.najm-bahar.transfer.preview');

    // گزارش‌های مالی مدیران و بازرسان گروه
    Route::get('groups/{group}/najm-bahar/reports/leaders', [\App\Http\Controllers\NajmBaharReportController::class, 'indexForGroupLeadersList'])->name('groups.najm-bahar.leader-reports.list');
    Route::get('groups/{group}/najm-bahar/reports/leaders/{leader}', [\App\Http\Controllers\NajmBaharReportController::class, 'indexForGroupLeader'])->name('groups.najm-bahar.leader-reports');
    Route::get('groups/{group}/najm-bahar/reports/leaders/{leader}/export-pdf', [\App\Http\Controllers\NajmBaharReportController::class, 'exportPdfForGroupLeader'])->name('groups.najm-bahar.leader-reports.export-pdf');
    Route::get('groups/{group}/najm-bahar/reports/leaders/{leader}/export-excel', [\App\Http\Controllers\NajmBaharReportController::class, 'exportExcelForGroupLeader'])->name('groups.najm-bahar.leader-reports.export-excel');

    // گزارش عملیات مالی گروه نجم بهار
    Route::get('groups/{group}/najm-bahar/audit-logs', [\App\Http\Controllers\NajmBaharAuditLogController::class, 'indexForGroup'])->name('groups.najm-bahar.audit-logs');
    Route::get('groups/{group}/najm-bahar/audit-logs/export', [\App\Http\Controllers\NajmBaharAuditLogController::class, 'exportForGroup'])->name('groups.najm-bahar.audit-logs.export');
    
    // حساب‌های فرعی نجم بهار
    Route::get('najm-bahar/sub-accounts', [\App\Http\Controllers\NajmBaharSubAccountController::class, 'index'])->name('najm-bahar.sub-accounts.index');
    Route::get('najm-bahar/sub-accounts/create', [\App\Http\Controllers\NajmBaharSubAccountController::class, 'create'])->name('najm-bahar.sub-accounts.create');
    Route::post('najm-bahar/sub-accounts', [\App\Http\Controllers\NajmBaharSubAccountController::class, 'store'])->name('najm-bahar.sub-accounts.store');
    Route::get('najm-bahar/sub-accounts/{subAccount}', [\App\Http\Controllers\NajmBaharSubAccountController::class, 'show'])->name('najm-bahar.sub-accounts.show');
    Route::put('najm-bahar/sub-accounts/{subAccount}', [\App\Http\Controllers\NajmBaharSubAccountController::class, 'update'])->name('najm-bahar.sub-accounts.update');
    Route::post('najm-bahar/sub-accounts/{subAccount}/transfer-to', [\App\Http\Controllers\NajmBaharSubAccountController::class, 'transferTo'])->name('najm-bahar.sub-accounts.transfer-to');
    Route::post('najm-bahar/sub-accounts/{subAccount}/transfer-from', [\App\Http\Controllers\NajmBaharSubAccountController::class, 'transferFrom'])->name('najm-bahar.sub-accounts.transfer-from');
    Route::post('najm-bahar/sub-accounts/{subAccount}/close', [\App\Http\Controllers\NajmBaharSubAccountController::class, 'closeWithTransfer'])->name('najm-bahar.sub-accounts.close');
    Route::post('najm-bahar/sub-accounts/{subAccount}/deactivate', [\App\Http\Controllers\NajmBaharSubAccountController::class, 'deactivate'])->name('najm-bahar.sub-accounts.deactivate');
    Route::post('najm-bahar/sub-accounts/{subAccount}/activate', [\App\Http\Controllers\NajmBaharSubAccountController::class, 'activate'])->name('najm-bahar.sub-accounts.activate');
    
    Route::get('spring-accounts', function () {
        return redirect()->route('najm-bahar.agreement');
    })->name('spring-accounts');

    Route::get('spring-accounts/agreement', function () {
        return redirect()->route('najm-bahar.agreement');
    })->name('spring-accounts.agreement');

    Route::post('/najm/confirm', function () {
        return redirect()->route('najm-bahar.agreement')
            ->with('info', 'توافقنامه فقط از مسیر ماژول نجم بهار قابل تایید است.');
    })->name('najm.confirm');

});

/*
|--------------------------------------------------------------------------
| Admin Panel
|--------------------------------------------------------------------------
*/

Route::middleware(AdminMiddleware::class)->prefix('admin')->name('admin.')->group(function () {

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // مدیریت محتوا
    Route::middleware('permission:blog.view-dashboard')->get('content', [ContentManagementController::class, 'index'])->name('content.index');
    
    // تنظیمات سیستمی
    Route::get('system-settings', [SystemSettingsController::class, 'index'])->name('system-settings.index');
    // Reputation admin
    Route::get('system-settings/reputation', [\App\Http\Controllers\Admin\ReputationController::class, 'index'])->name('reputation.index');
    Route::post('system-settings/reputation', [\App\Http\Controllers\Admin\ReputationController::class, 'update'])->name('reputation.update');

    // نجم‌هدا - مدیریت هوش مصنوعی
    Route::middleware('permission:najm-hoda.view-dashboard')->prefix('najm-hoda')->name('najm-hoda.')->group(function () {
        Route::get('/', [AdminNajmHodaController::class, 'index'])->name('index');
        Route::middleware('permission:najm-hoda.view-conversations')->group(function () {
            Route::get('/conversations', [AdminNajmHodaController::class, 'conversations'])->name('conversations');
            Route::get('/conversations/{conversation}', [AdminNajmHodaController::class, 'showConversation'])->name('conversations.show');
        });
        Route::get('/analytics', [AdminNajmHodaController::class, 'analytics'])->name('analytics');
        Route::get('/feedbacks', [AdminNajmHodaController::class, 'feedbacks'])->name('feedbacks');
        Route::middleware('permission:najm-hoda.manage-settings')->group(function () {
            Route::get('/settings', [AdminNajmHodaController::class, 'settings'])->name('settings');
            Route::post('/settings', [AdminNajmHodaController::class, 'updateSettings'])->name('settings.update');
            Route::get('/group-action-items', [AdminNajmHodaController::class, 'groupActionItems'])->name('group-action-items');
            Route::put('/group-action-items/{actionItem}', [AdminNajmHodaController::class, 'updateGroupActionItem'])->name('group-action-items.update');
            
            // Steward Knowledge Files - داخل manage-settings
            Route::get('/steward/knowledge-files', [AdminNajmHodaController::class, 'manageKnowledgeFiles'])->name('steward.knowledge-files-page');
            Route::post('/steward/upload-knowledge', [AdminNajmHodaController::class, 'uploadKnowledgeFile'])->name('steward.upload-knowledge');
            Route::get('/steward/knowledge-files-api', [AdminNajmHodaController::class, 'getKnowledgeFiles'])->name('steward.knowledge-files-api');
            Route::put('/steward/knowledge-files/{id}', [AdminNajmHodaController::class, 'updateKnowledgeFile'])->name('steward.update-knowledge');
            Route::delete('/steward/knowledge-files/{id}', [AdminNajmHodaController::class, 'deleteKnowledgeFile'])->name('steward.delete-knowledge');
        });
        Route::get('/chat', [AdminNajmHodaController::class, 'chat'])->name('chat');
        Route::post('/chat', [AdminNajmHodaController::class, 'sendMessage'])->name('chat.send');
        Route::get('/create-agent', [AdminNajmHodaController::class, 'createAgent'])->name('create-agent');
        Route::post('/design-agent', [AdminNajmHodaController::class, 'designAgent'])->name('design-agent');
        Route::post('/save-agent', [AdminNajmHodaController::class, 'saveAgent'])->name('save-agent');
        Route::get('/logs', [AdminNajmHodaController::class, 'logs'])->name('logs');
        Route::delete('/logs', [AdminNajmHodaController::class, 'clearLogs'])->name('logs.clear');
        Route::get('/ops', [AdminNajmHodaController::class, 'opsDigestPage'])->name('ops.page');
        Route::get('/ops/digest', [AdminNajmHodaController::class, 'getOpsDigest'])->name('ops.digest');
        Route::middleware('permission:najm-hoda.manage-settings,najm-hoda.autonomy.read')->group(function () {
            Route::get('/autonomy/approvals', [AdminNajmHodaController::class, 'getAutonomyApprovals'])->middleware('throttle:najm-hoda-autonomy-read')->name('autonomy.approvals');
            Route::get('/autonomy/oversight/console', [AdminNajmHodaController::class, 'getAutonomyOversightConsole'])->middleware('throttle:najm-hoda-autonomy-read')->name('autonomy.oversight.console');
            Route::post('/autonomy/oversight/telemetry', [AdminNajmHodaController::class, 'recordAutonomyOversightTelemetry'])->middleware('throttle:najm-hoda-autonomy-read')->name('autonomy.oversight.telemetry');
            Route::get('/autonomy/policy-learning/recommendations', [AdminNajmHodaController::class, 'getAutonomyPolicyLearningRecommendations'])->middleware('throttle:najm-hoda-autonomy-read')->name('autonomy.policy-learning.recommendations');
            Route::get('/autonomy/codeops/canary', [AdminNajmHodaController::class, 'getAutonomyCodeOpsCanary'])->middleware('throttle:najm-hoda-autonomy-read')->name('autonomy.codeops.canary');
            Route::get('/autonomy/evaluation/report', [AdminNajmHodaController::class, 'getAutonomyContinuousEvaluation'])->middleware('throttle:najm-hoda-autonomy-read')->name('autonomy.evaluation.report');
            Route::get('/autonomy/evaluation/export', [AdminNajmHodaController::class, 'exportAutonomyContinuousEvaluation'])->middleware('throttle:najm-hoda-autonomy-read')->name('autonomy.evaluation.export');
            Route::get('/autonomy/operations/status', [AdminNajmHodaController::class, 'getAutonomyOperationsStatus'])->middleware('throttle:najm-hoda-autonomy-read')->name('autonomy.operations.status');
            Route::get('/autonomy/shadow-rollout/status', [AdminNajmHodaController::class, 'getAutonomyShadowRolloutStatus'])->middleware('throttle:najm-hoda-autonomy-read')->name('autonomy.shadow-rollout.status');
            Route::get('/autonomy/controls', [AdminNajmHodaController::class, 'getAutonomyControls'])->middleware('throttle:najm-hoda-autonomy-read')->name('autonomy.controls');
            Route::get('/autonomy/audit', [AdminNajmHodaController::class, 'getAutonomyAuditTraces'])->middleware('throttle:najm-hoda-autonomy-read')->name('autonomy.audit');
            Route::get('/autonomy/governance', [AdminNajmHodaController::class, 'autonomyGovernancePage'])->name('autonomy.governance.page');
            Route::get('/autonomy/governance/baseline', [AdminNajmHodaController::class, 'getAutonomyGovernanceBaseline'])->name('autonomy.governance.baseline');
            Route::get('/autonomy/governance/snapshot', [AdminNajmHodaController::class, 'getAutonomyGovernanceSnapshot'])->name('autonomy.governance.snapshot');
            Route::get('/autonomy/governance/drift', [AdminNajmHodaController::class, 'getAutonomyGovernanceDriftReport'])->name('autonomy.governance.drift');
            Route::get('/autonomy/governance/alerts/history', [AdminNajmHodaController::class, 'getAutonomyGovernanceAlertHistory'])->middleware('throttle:najm-hoda-autonomy-read')->name('autonomy.governance.alerts.history');
            Route::get('/autonomy/gameday/history', [AdminNajmHodaController::class, 'getAutonomyGameDayHistory'])->middleware('throttle:najm-hoda-autonomy-read')->name('autonomy.gameday.history');
            Route::get('/autonomy/runbooks', [AdminNajmHodaController::class, 'getAutonomyRunbookRegistry'])->name('autonomy.runbooks.registry');
            Route::get('/autonomy/runbooks/readiness', [AdminNajmHodaController::class, 'getAutonomyRunbookReadiness'])->name('autonomy.runbooks.readiness');
            Route::get('/autonomy/costs/status', [AdminNajmHodaController::class, 'getAutonomyCostStatus'])->name('autonomy.costs.status');
            Route::get('/autonomy/compliance/evidence', [AdminNajmHodaController::class, 'getAutonomyComplianceEvidence'])->middleware('throttle:najm-hoda-autonomy-read')->name('autonomy.compliance.evidence');
            Route::get('/autonomy/compliance/evidence/export', [AdminNajmHodaController::class, 'exportAutonomyComplianceEvidence'])->middleware('throttle:najm-hoda-autonomy-read')->name('autonomy.compliance.export');
            Route::get('/autonomy/readiness/review', [AdminNajmHodaController::class, 'getAutonomyReadinessReview'])->middleware('throttle:najm-hoda-autonomy-read')->name('autonomy.readiness.review');
            Route::get('/autonomy/readiness/review/export', [AdminNajmHodaController::class, 'exportAutonomyReadinessReview'])->middleware('throttle:najm-hoda-autonomy-read')->name('autonomy.readiness.export');
            Route::get('/autonomy/phase6/signoff/report', [AdminNajmHodaController::class, 'getAutonomyPhaseSixSignoffReport'])->middleware('throttle:najm-hoda-autonomy-read')->name('autonomy.phase6-signoff.report');
        });
        Route::middleware('permission:najm-hoda.manage-settings,najm-hoda.autonomy.write')->group(function () {
            Route::post('/autonomy/approvals/{approvalId}/decision', [AdminNajmHodaController::class, 'decideAutonomyApproval'])->middleware('throttle:najm-hoda-autonomy-write')->name('autonomy.approvals.decision');
            Route::post('/autonomy/approvals/{approvalId}/veto', [AdminNajmHodaController::class, 'vetoAutonomyApproval'])->middleware('throttle:najm-hoda-autonomy-write')->name('autonomy.approvals.veto');
            Route::post('/autonomy/policy-learning/recommendations/{recommendationId}/review', [AdminNajmHodaController::class, 'reviewAutonomyPolicyLearningRecommendation'])->middleware('throttle:najm-hoda-autonomy-write')->name('autonomy.policy-learning.review');
            Route::post('/autonomy/codeops/canary', [AdminNajmHodaController::class, 'updateAutonomyCodeOpsCanary'])->middleware('throttle:najm-hoda-autonomy-write')->name('autonomy.codeops.canary.update');
            Route::post('/autonomy/evaluation/run', [AdminNajmHodaController::class, 'runAutonomyContinuousEvaluation'])->middleware('throttle:najm-hoda-autonomy-write')->name('autonomy.evaluation.run');
            Route::post('/autonomy/operations/status', [AdminNajmHodaController::class, 'updateAutonomyOperationsStatus'])->middleware('throttle:najm-hoda-autonomy-write')->name('autonomy.operations.update');
            Route::post('/autonomy/shadow-rollout/status', [AdminNajmHodaController::class, 'updateAutonomyShadowRolloutStatus'])->middleware('throttle:najm-hoda-autonomy-write')->name('autonomy.shadow-rollout.update');
            Route::post('/autonomy/controls', [AdminNajmHodaController::class, 'updateAutonomyControls'])->middleware('throttle:najm-hoda-autonomy-write')->name('autonomy.controls.update');
            Route::post('/autonomy/audit/{runId}/replay', [AdminNajmHodaController::class, 'replayAutonomyTrace'])->middleware('throttle:najm-hoda-autonomy-write')->name('autonomy.audit.replay');
            Route::post('/autonomy/governance/alerts/evaluate', [AdminNajmHodaController::class, 'evaluateAutonomyGovernanceAlerts'])->middleware('throttle:najm-hoda-autonomy-write')->name('autonomy.governance.alerts.evaluate');
            Route::post('/autonomy/phase6/signoff', [AdminNajmHodaController::class, 'updateAutonomyPhaseSixSignoff'])->middleware('throttle:najm-hoda-autonomy-write')->name('autonomy.phase6-signoff.update');
        });
        Route::post('/autonomy/gameday/run', [AdminNajmHodaController::class, 'runAutonomyGameDay'])
            ->middleware(['permission:najm-hoda.manage-settings,najm-hoda.autonomy.gameday,najm-hoda.autonomy.write', 'throttle:najm-hoda-autonomy-write'])
            ->name('autonomy.gameday.run');
        
        // Code Scanner
        Route::middleware('permission:najm-hoda.use-code-scanner')->group(function () {
            Route::get('/code-scanner', [AdminNajmHodaController::class, 'codeScanner'])->name('code-scanner');
            Route::post('/code-scanner/scan', [AdminNajmHodaController::class, 'scanProject'])->name('code-scanner.scan');
            Route::get('/code-scanner/results', [AdminNajmHodaController::class, 'scanProject'])->name('code-scanner.results');
            Route::post('/code-scanner/analyze-file', [AdminNajmHodaController::class, 'analyzeFile'])->name('code-scanner.analyze-file');
            Route::post('/code-scanner/suggestion', [AdminNajmHodaController::class, 'getSuggestion'])->name('code-scanner.suggestion');
        });
        
        // Auto-Fixer Settings
        Route::get('/auto-fixer-settings', [AdminNajmHodaController::class, 'autoFixerSettings'])->name('auto-fixer-settings');
        Route::get('/auto-fixer/settings', [AdminNajmHodaController::class, 'getAutoFixerSettings'])->name('auto-fixer.settings.get');
        Route::post('/auto-fixer/settings', [AdminNajmHodaController::class, 'saveAutoFixerSettings'])->name('auto-fixer.settings.save');
        Route::post('/auto-fixer/test-run', [AdminNajmHodaController::class, 'testAutoFixer'])->name('auto-fixer.test');
        Route::post('/auto-fixer/clean-backups', [AdminNajmHodaController::class, 'cleanBackups'])->name('auto-fixer.clean-backups');
        Route::get('/auto-fixer/logs', [AdminNajmHodaController::class, 'getAutoFixerLogs'])->name('auto-fixer.logs');
        Route::post('/auto-fixer/rollback', [AdminNajmHodaController::class, 'rollback'])->name('auto-fixer.rollback');
    });

    // Support tickets management
    Route::middleware('permission:tickets.manage')->prefix('tickets')->name('tickets.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\TicketController::class, 'index'])->name('index');
        Route::get('/export', [\App\Http\Controllers\Admin\TicketController::class, 'export'])->name('export');
        Route::post('/bulk-action', [\App\Http\Controllers\Admin\TicketController::class, 'bulkAction'])->name('bulk-action');
        Route::get('/{ticket}', [\App\Http\Controllers\Admin\TicketController::class, 'show'])->name('show');
        Route::post('/{ticket}/assign', [\App\Http\Controllers\Admin\TicketController::class, 'assign'])->name('assign');
        Route::post('/{ticket}/reply', [\App\Http\Controllers\Admin\TicketController::class, 'reply'])->name('reply');
        Route::post('/{ticket}/close', [\App\Http\Controllers\Admin\TicketController::class, 'close'])->name('close');
    });

    // Email management
    Route::prefix('emails')->name('emails.')->group(function () {
        Route::get('/', [EmailController::class, 'index'])->name('index');
        Route::get('/create', [EmailController::class, 'create'])->name('create');
        Route::post('/', [EmailController::class, 'store'])->name('store');
        Route::get('/send', [EmailController::class, 'showSendForm'])->name('send');
        Route::post('/send-template', [EmailController::class, 'sendTemplate'])->name('send-template');
        Route::post('/send-custom', [EmailController::class, 'sendCustom'])->name('send-custom');
        Route::get('/{email}/edit', [EmailController::class, 'edit'])->name('edit');
        Route::put('/{email}', [EmailController::class, 'update'])->name('update');
        Route::delete('/{email}', [EmailController::class, 'destroy'])->name('destroy');
        Route::post('/{email}/preview', [EmailController::class, 'preview'])->name('preview');
    });

    // System emails management
    Route::prefix('system-emails')->name('system-emails.')->group(function () {
        Route::get('/', [SystemEmailController::class, 'index'])->name('index');
        Route::get('/create', [SystemEmailController::class, 'create'])->name('create');
        Route::post('/', [SystemEmailController::class, 'store'])->name('store');
        Route::get('/{systemEmail}/edit', [SystemEmailController::class, 'edit'])->name('edit');
        Route::put('/{systemEmail}', [SystemEmailController::class, 'update'])->name('update');
        Route::delete('/{systemEmail}', [SystemEmailController::class, 'destroy'])->name('destroy');
        Route::post('/{systemEmail}/set-default', [SystemEmailController::class, 'setDefault'])->name('set-default');
    });

    // مدیریت گروه‌ها
    Route::middleware('permission:groups.view')->prefix('groups')->name('groups.')->group(function () {
        Route::get('/', [AdminGroupController::class, 'index'])->name('index');
        Route::get('/{group}/manage', [GroupManagementController::class, 'manage'])->name('manage');
        
        Route::middleware('permission:groups.manage-members')->group(function () {
            Route::put('/manage/change-roles/{group}', function(Request $request, \App\Models\Group $group){
                $validated = $request->validate([
                    'users' => 'required|array|min:1',
                    'users.*' => 'exists:users,id',
                    'main_role' => 'required|in:0,1,2,3',
                ]);
                
                // فقط نقش کاربران انتخاب شده را تغییر می‌دهیم
                foreach ($validated['users'] as $userId) {
                    $group->users()->updateExistingPivot($userId, [
                        'role' => $validated['main_role'],
                        'main_role' => $validated['main_role']
                    ]);
                }
                
                return back()->with('success', 'نقش‌های کاربران با موفقیت تغییر کرد');
            })->name('changeRoles');
            Route::put('/{group}/users/{user}/role', [GroupManagementController::class, 'updateRole'])->name('updateRole');
        });
        
        Route::middleware('permission:groups.manage-settings')->group(function () {
            Route::put('/{group}', [AdminGroupController::class, 'update'])->name('update');
            Route::delete('/{group}', [AdminGroupController::class, 'delete'])->name('delete');
        });
    });

    Route::get('faq-questions', [AdminFaqQuestionController::class, 'index'])->name('faq.index');
    Route::put('faq-questions/{question}', [AdminFaqQuestionController::class, 'update'])->name('faq.update');
    Route::delete('faq-questions/{question}', [AdminFaqQuestionController::class, 'destroy'])->name('faq.destroy');
    Route::post('faq-questions/bulk', [AdminFaqQuestionController::class, 'bulkAction'])->name('faq.bulk');
    
    Route::put('groups/manage/chage-roles/{group}', function(Request $request, \App\Models\Group $group){
        $validated = $request->validate([
            'users' => 'required|array|min:1',
            'users.*' => 'exists:users,id',
            'main_role' => 'required|in:0,1,2,3', // بر اساس نقش‌های موجود
        ]);

        foreach ($validated['users'] as $userId) {
            $groupUser = \App\Models\GroupUser::where('group_id', $group->id)->where('user_id', $userId)->first();
            $groupUser->role = $validated['main_role'];
            $groupUser->save();
            
        }
    
        return back()->with('success', 'نقش کاربران با موفقیت تغییر یافت.');    
    })->name('groups.change-roles');
    

    Route::put('group-post-update/{blog}', [AdminGroupController::class, 'postUpdate'])->name('group-post.update');
    Route::get('group-post-delete/{blog}', [AdminGroupController::class, 'postDelete'])->name('group.post.delete');

    // کدهای دعوت
    Route::get('invitation-codes', [InvitationCodeController::class, 'index'])->name('invitation_codes.index');
    Route::post('invitation-codes', [InvitationCodeController::class, 'store'])->name('invitation_codes.store');
    Route::post('invitation-codes/bulk', [InvitationCodeController::class, 'bulkAction'])->name('invitation_codes.bulk');
    Route::post('invitation-codes/generate', [InvitationCodeController::class, 'generate'])->name('invitation_codes.generate');
    Route::get('invitation-codes/export', [InvitationCodeController::class, 'exportCsv'])->name('invitation_codes.export');
    Route::get('invitation-codes/logs', [InvitationCodeController::class, 'logs'])->name('invitation_codes.logs');
    Route::get('invitation-codes/logs/export', [InvitationCodeController::class, 'exportLogs'])->name('invitation_codes.logs.export');
    Route::post('invitation-codes/auto-invalidate', [InvitationCodeController::class, 'autoInvalidate'])->name('invitation_codes.auto_invalidate');

    // درخواست‌های کد دعوت
    Route::post('invitation-requests/{invitation}/approve', [InvitationCodeController::class, 'approveInvitation'])->name('invitation_requests.approve');
    Route::post('invitation-requests/{invitation}/reject', [InvitationCodeController::class, 'rejectInvitation'])->name('invitation_requests.reject');
    Route::post('invitation-requests/bulk', [InvitationCodeController::class, 'bulkRequests'])->name('invitation_requests.bulk');

    Route::get('/activate', [ActivateController::class, 'index'])->name('activate.index'); 
    Route::put('/activate', [ActivateController::class, 'update'])->name('activate.update');

    Route::get('categories', [\App\Http\Controllers\Admin\CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [\App\Http\Controllers\Admin\CategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [\App\Http\Controllers\Admin\CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [\App\Http\Controllers\Admin\CategoryController::class, 'destroy'])->name('categories.destroy');
    //اساسنامه
    Route::get('rule', [RuleController::class, 'index'])->name('rule.index');
    Route::get('rule/create', [RuleController::class, 'create'])->name('rule.create');
    Route::post('rule', [RuleController::class, 'store'])->name('rule.store');
    Route::get('rule/{rule}/edit', [RuleController::class, 'edit'])->name('rule.edit');
    Route::put('rule/{rule}', [RuleController::class, 'update'])->name('rule.update');
    Route::delete('rule/{rule}', [RuleController::class, 'destroy'])->name('rule.destroy');
    
    // توافقنامه‌های نجم بهار
    Route::get('najm-bahar', [NajmBaharController::class, 'index'])->name('najm-bahar.index');
    Route::get('najm-bahar/create', [NajmBaharController::class, 'create'])->name('najm-bahar.create');
    Route::post('najm-bahar', [NajmBaharController::class, 'store'])->name('najm-bahar.store');
    Route::get('najm-bahar/{najmBahar}/edit', [NajmBaharController::class, 'edit'])->name('najm-bahar.edit');
    Route::put('najm-bahar/{najmBahar}', [NajmBaharController::class, 'update'])->name('najm-bahar.update');
    Route::delete('najm-bahar/{najmBahar}', [NajmBaharController::class, 'destroy'])->name('najm-bahar.destroy');
    
    // مدیریت کارمزدهای نجم بهار
    Route::get('najm-bahar/fees', [\App\Http\Controllers\Admin\NajmBaharFeeController::class, 'index'])->name('najm-bahar.fees.index');
    Route::get('najm-bahar/fees/create', [\App\Http\Controllers\Admin\NajmBaharFeeController::class, 'create'])->name('najm-bahar.fees.create');
    Route::post('najm-bahar/fees', [\App\Http\Controllers\Admin\NajmBaharFeeController::class, 'store'])->name('najm-bahar.fees.store');
    Route::get('najm-bahar/fees/{fee}/edit', [\App\Http\Controllers\Admin\NajmBaharFeeController::class, 'edit'])->name('najm-bahar.fees.edit');
    Route::put('najm-bahar/fees/{fee}', [\App\Http\Controllers\Admin\NajmBaharFeeController::class, 'update'])->name('najm-bahar.fees.update');
    Route::delete('najm-bahar/fees/{fee}', [\App\Http\Controllers\Admin\NajmBaharFeeController::class, 'destroy'])->name('najm-bahar.fees.destroy');
    Route::post('najm-bahar/fees/test', [\App\Http\Controllers\Admin\NajmBaharFeeController::class, 'test'])->name('najm-bahar.fees.test');

    // تنظیمات نجم بهار
    Route::get('najm-bahar/settings', [\App\Http\Controllers\Admin\NajmBaharSettingsController::class, 'index'])->name('najm-bahar.settings.index');
    Route::put('najm-bahar/settings', [\App\Http\Controllers\Admin\NajmBaharSettingsController::class, 'update'])->name('najm-bahar.settings.update');
    
    // داشبورد مدیریت نجم بهار
    Route::get('najm-bahar/dashboard', [\App\Http\Controllers\Admin\NajmBaharDashboardController::class, 'index'])->name('najm-bahar.dashboard');

    // مدیریت حقوق نجم بهار
    Route::get('najm-bahar/salaries', [\App\Http\Controllers\Admin\NajmBaharSalaryRuleController::class, 'index'])->name('najm-bahar.salaries.index');
    Route::get('najm-bahar/salaries/create', [\App\Http\Controllers\Admin\NajmBaharSalaryRuleController::class, 'create'])->name('najm-bahar.salaries.create');
    Route::post('najm-bahar/salaries', [\App\Http\Controllers\Admin\NajmBaharSalaryRuleController::class, 'store'])->name('najm-bahar.salaries.store');
    Route::get('najm-bahar/salaries/{salaryRule}/edit', [\App\Http\Controllers\Admin\NajmBaharSalaryRuleController::class, 'edit'])->name('najm-bahar.salaries.edit');
    Route::put('najm-bahar/salaries/{salaryRule}', [\App\Http\Controllers\Admin\NajmBaharSalaryRuleController::class, 'update'])->name('najm-bahar.salaries.update');
    Route::delete('najm-bahar/salaries/{salaryRule}', [\App\Http\Controllers\Admin\NajmBaharSalaryRuleController::class, 'destroy'])->name('najm-bahar.salaries.destroy');

    // اجرای حقوق نجم بهار
    Route::get('najm-bahar/salary-runs', [\App\Http\Controllers\Admin\NajmBaharSalaryRunController::class, 'index'])->name('najm-bahar.salary-runs.index');
    Route::post('najm-bahar/salary-runs', [\App\Http\Controllers\Admin\NajmBaharSalaryRunController::class, 'store'])->name('najm-bahar.salary-runs.store');
    Route::get('najm-bahar/salary-runs/{salaryRun}', [\App\Http\Controllers\Admin\NajmBaharSalaryRunController::class, 'show'])->name('najm-bahar.salary-runs.show');
    Route::post('najm-bahar/salary-runs/{salaryRun}/process', [\App\Http\Controllers\Admin\NajmBaharSalaryRunController::class, 'process'])->name('najm-bahar.salary-runs.process');
    Route::put('najm-bahar/salary-runs/{salaryRun}/items/{item}', [\App\Http\Controllers\Admin\NajmBaharSalaryRunController::class, 'updateItem'])->name('najm-bahar.salary-runs.items.update');
    
    // Dashboard ادمین نجم بهار
    Route::get('najm-bahar/dashboard', [\App\Http\Controllers\Admin\NajmBaharDashboardController::class, 'index'])->name('najm-bahar.dashboard');
    Route::post('najm-bahar/dashboard/threshold', [\App\Http\Controllers\Admin\NajmBaharDashboardController::class, 'updateUserThreshold'])->name('najm-bahar.threshold.update');
    Route::post('najm-bahar/dashboard/initial-amount', [\App\Http\Controllers\Admin\NajmBaharDashboardController::class, 'updateInitialAmount'])->name('najm-bahar.initial-amount.update');
    Route::post('najm-bahar/dashboard/membership-split', [\App\Http\Controllers\Admin\NajmBaharDashboardController::class, 'updateMembershipSplit'])->name('najm-bahar.membership-split.update');

    // مدیریت حساب‌های سیستمی نجم بهار
    Route::get('najm-bahar/system-accounts', [\App\Http\Controllers\Admin\NajmBaharSystemAccountsController::class, 'index'])->name('najm-bahar.system-accounts.index');
    Route::post('najm-bahar/system-accounts/ensure-defaults', [\App\Http\Controllers\Admin\NajmBaharSystemAccountsController::class, 'ensureDefaults'])->name('najm-bahar.system-accounts.ensure-defaults');
    Route::post('najm-bahar/system-accounts/sub-accounts', [\App\Http\Controllers\Admin\NajmBaharSystemAccountsController::class, 'storeSubAccount'])->name('najm-bahar.system-accounts.sub-accounts.store');
    Route::put('najm-bahar/system-accounts/sub-accounts/{subAccount}', [\App\Http\Controllers\Admin\NajmBaharSystemAccountsController::class, 'updateSubAccount'])->name('najm-bahar.system-accounts.sub-accounts.update');
    Route::post('najm-bahar/system-accounts/transfer', [\App\Http\Controllers\Admin\NajmBaharSystemAccountsController::class, 'transfer'])->name('najm-bahar.system-accounts.transfer');
    Route::post('najm-bahar/system-accounts/adjust', [\App\Http\Controllers\Admin\NajmBaharSystemAccountsController::class, 'adjust'])->name('najm-bahar.system-accounts.adjust');

    // مدیریت همه حساب‌های نجم بهار
    Route::get('najm-bahar/accounts', [\App\Http\Controllers\Admin\NajmBaharAccountsController::class, 'index'])->name('najm-bahar.accounts.index');
    Route::get('najm-bahar/accounts/{accountNumber}/transactions', [\App\Http\Controllers\Admin\NajmBaharAccountsController::class, 'transactions'])->name('najm-bahar.accounts.transactions');

    // لاگ‌های مدیریتی نجم بهار
    Route::get('najm-bahar/logs', [\App\Http\Controllers\Admin\NajmBaharLogsController::class, 'index'])->name('najm-bahar.logs.index');

    // گزارش عملیات مالی نجم بهار
    Route::get('najm-bahar/audit-logs', [\App\Http\Controllers\Admin\NajmBaharAuditLogController::class, 'index'])->name('najm-bahar.audit-logs.index');
    Route::get('najm-bahar/audit-logs/export', [\App\Http\Controllers\Admin\NajmBaharAuditLogController::class, 'export'])->name('najm-bahar.audit-logs.export');

    // مدیریت حقوق نجم بهار
    Route::get('najm-bahar/salaries', [\App\Http\Controllers\Admin\NajmBaharSalaryRuleController::class, 'index'])->name('najm-bahar.salaries.index');
    Route::get('najm-bahar/salaries/create', [\App\Http\Controllers\Admin\NajmBaharSalaryRuleController::class, 'create'])->name('najm-bahar.salaries.create');
    Route::post('najm-bahar/salaries', [\App\Http\Controllers\Admin\NajmBaharSalaryRuleController::class, 'store'])->name('najm-bahar.salaries.store');
    Route::get('najm-bahar/salaries/{salaryRule}/edit', [\App\Http\Controllers\Admin\NajmBaharSalaryRuleController::class, 'edit'])->name('najm-bahar.salaries.edit');
    Route::put('najm-bahar/salaries/{salaryRule}', [\App\Http\Controllers\Admin\NajmBaharSalaryRuleController::class, 'update'])->name('najm-bahar.salaries.update');
    Route::delete('najm-bahar/salaries/{salaryRule}', [\App\Http\Controllers\Admin\NajmBaharSalaryRuleController::class, 'destroy'])->name('najm-bahar.salaries.destroy');

    Route::get('najm-bahar/salary-runs', [\App\Http\Controllers\Admin\NajmBaharSalaryRunController::class, 'index'])->name('najm-bahar.salary-runs.index');
    Route::post('najm-bahar/salary-runs', [\App\Http\Controllers\Admin\NajmBaharSalaryRunController::class, 'store'])->name('najm-bahar.salary-runs.store');
    Route::get('najm-bahar/salary-runs/{salaryRun}', [\App\Http\Controllers\Admin\NajmBaharSalaryRunController::class, 'show'])->name('najm-bahar.salary-runs.show');
    Route::post('najm-bahar/salary-runs/{salaryRun}/process', [\App\Http\Controllers\Admin\NajmBaharSalaryRunController::class, 'process'])->name('najm-bahar.salary-runs.process');
    Route::put('najm-bahar/salary-runs/{salaryRun}/items/{item}', [\App\Http\Controllers\Admin\NajmBaharSalaryRunController::class, 'updateItem'])->name('najm-bahar.salary-runs.items.update');
    
    // گزارش‌های تحلیلی نجم بهار
    Route::get('najm-bahar/analytics', [\App\Http\Controllers\Admin\NajmBaharAnalyticsController::class, 'index'])->name('najm-bahar.analytics');
    
    // صفحه قدیمی نجم بهار (برای سازگاری با سیستم قدیمی)
    Route::get('/najm-page', function(){
        return redirect()->route('admin.najm-bahar.index');
    })->name('najm-page');
    
    // مدیریت صفحه خوش‌آمد و هوم
    Route::get('/welcome-page', [WelcomePageController::class, 'index'])->name('welcome-page');
    Route::put('/welcome-page', [WelcomePageController::class, 'update'])->name('welcome-page.update');
    Route::post('/welcome-page/slider', [WelcomePageController::class, 'storeSlider'])->name('welcome-page.slider.store');
    Route::delete('/welcome-page/slider/{slider}', [WelcomePageController::class, 'destroySlider'])->name('welcome-page.slider.destroy');
    
    //اساسنامه
    // User routes - باید قبل از resource قرار بگیرند تا تداخل نداشته باشند
    Route::middleware('permission:users.view')->prefix('users')->name('users.')->group(function () {
        Route::get('/export/all', [UserController::class, 'exportUsers'])->name('export.all');
        Route::get('/import', [UserController::class, 'showImport'])->name('import');
        Route::post('/import', [UserController::class, 'import'])->name('import.store');
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/{user}/details', [UserController::class, 'show'])->name('show');
        Route::get('/{user}/transactions', [UserController::class, 'transactions'])->name('transactions');
        
        Route::middleware('permission:users.create')->group(function () {
            Route::get('/create', [UserController::class, 'create'])->name('create');
            Route::post('/', [UserController::class, 'store'])->name('store');
        });
        
        Route::middleware('permission:users.edit')->group(function () {
            Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
            Route::put('/{user}', [UserController::class, 'update'])->name('update');
        });
        
        Route::middleware('permission:users.manage-status')->group(function () {
            Route::post('/bulk-action', [UserController::class, 'bulkAction'])->name('bulkAction');
            Route::post('/{user}/status', [UserController::class, 'updateStatus'])->name('updateStatus');
        });
        
        Route::middleware('permission:users.reset-password')->group(function () {
            Route::post('/{user}/reset-password', [UserController::class, 'resetPassword'])->name('resetPassword');
        });
        
        Route::middleware('permission:users.export')->group(function () {
            Route::get('/export/all', [UserController::class, 'exportUsers'])->name('export.all');
        });
        
        Route::middleware('permission:roles.manage')->group(function () {
            Route::post('/{user}/assign-role', [UserController::class, 'assignRole'])->name('assignRole');
        });
        
        Route::get('/{user}/send-message', [UserController::class, 'showSendMessage'])->name('sendMessage');
        Route::post('/{user}/send-message', [UserController::class, 'sendMessage'])->name('sendMessage.store');
        Route::post('/{user}/force-logout', [UserController::class, 'forceLogout'])->name('forceLogout');
        
        Route::middleware('permission:users.delete')->group(function () {
            Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
        });
    });
    
    // مدیریت نقش‌ها و دسترسی‌ها
    Route::middleware('permission:roles.manage')->group(function () {
        Route::resource('roles', \App\Http\Controllers\Admin\RoleController::class);
        Route::resource('permissions', \App\Http\Controllers\Admin\PermissionController::class);
    });

    Route::get('active-address', [AddressController::class, 'index'])->name('active.address');
    Route::post('active-address', [AddressController::class, 'store'])->name('active.address.store');
    Route::get('active-address/parents/{type}', [AddressController::class, 'getAvailableParents'])->name('active.address.parents');
    Route::get('active-address/edit/{id}', [AddressController::class, 'edit'])->name('active.address.edit');
    Route::put('active-address/{id}', [AddressController::class, 'update'])->name('active.address.update');
    Route::get('active-address/delete/{id}', [AddressController::class, 'delete'])->name('active.address.delete');
    Route::post('active-address/bulk-approve', [AddressController::class, 'bulkApprove'])->name('active.address.bulk.approve');
    Route::post('active-address/bulk-delete', [AddressController::class, 'bulkDelete'])->name('active.address.bulk.delete');


    Route::get('active-experience', [ExperienceController::class, 'index'])->name('active.experience');
    Route::post('active-experience', [ExperienceController::class, 'store'])->name('active.experience.store');
    Route::get('active-experience/parents/{type}', [ExperienceController::class, 'getAvailableParents'])->name('active.experience.parents');
    Route::put('active-experience/{id}', [ExperienceController::class, 'update'])->name('active.experience.update');
    Route::get('active-experience/edit/{id}', [ExperienceController::class, 'edit'])->name('active.experience.edit');
    Route::get('active-experience/delete/{id}', [ExperienceController::class, 'delete'])->name('active.experience.delete');
    Route::post('active-experience/bulk-approve', [ExperienceController::class, 'bulkApprove'])->name('active.experience.bulk.approve');
    Route::post('active-experience/bulk-delete', [ExperienceController::class, 'bulkDelete'])->name('active.experience.bulk.delete');

    Route::get('group-setting', [GroupSettingController::class, 'index'])->name('group.setting.index');
    Route::get('group-setting/change-status/{setting}', [GroupSettingController::class, 'edit'])->name('group.setting.edit');
    Route::put('group-setting/{setting}', [GroupSettingController::class, 'update'])->name('group.setting.update');

    Route::resource('announcement', AnnouncementController::class);
    Route::get('announcement/delete/{id}', [AnnouncementController::class, 'delete'])->name('announcement.delete');
    Route::post('announcement/{announcement}/unpin', [AnnouncementController::class, 'unpin'])->name('announcement.unpin');

    // گزارش پیام‌ها
    Route::post('/messages/{message}/report', [MessageController::class, 'report'])->name('messages.report');

    Route::resource('pages', PageController::class);
    Route::post('pages/upload', [PageController::class, 'upload'])->name('pages.upload');
    
    // Public page route
});
Route::get('pages/{slug}', [\App\Http\Controllers\PageController::class, 'show'])->name('pages.show');
Route::post('/faq/questions', [\App\Http\Controllers\FaqQuestionController::class, 'store'])->name('questions.store');

// Contact form (server-side ticket creation)
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

// Route تست ایمیل (فقط برای تست - بعداً حذف کنید)
Route::get('/admin/test-email', function() {
    if (!auth()->check() || !auth()->user()->hasRole('super_admin')) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }
    
    try {
        $testEmail = request('email', 'test@example.com');
        \Illuminate\Support\Facades\Mail::to($testEmail)->send(new \App\Mail\InvitationMail('TEST123', now()->addHours(72)));
        return response()->json([
            'success' => true,
            'message' => 'Test email sent successfully (check your inbox or logs)',
            'mail_config' => [
                'driver' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'from' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
            ]
        ]);
    } catch (\Exception $e) {
        \Log::error('Test email failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'mail_config' => [
                'driver' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'from' => config('mail.from.address'),
            ]
        ], 500);
    }
})->middleware(['auth', 'admin'])->name('admin.test-email');

// Route تست RBAC (فقط برای تست - بعداً حذف کنید)
Route::get('/test-rbac', function() {
    $user = auth()->user();
    
    if (!$user) {
        return response()->json([
            'error' => 'لطفا ابتدا وارد شوید'
        ], 401);
    }
    
    return response()->json([
        'user' => [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->fullName(),
            'is_admin' => $user->is_admin,
        ],
        'roles' => $user->roles->map(function($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ];
        }),
        'permissions' => $user->getAllPermissions()->map(function($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'slug' => $permission->slug,
                'module' => $permission->module,
            ];
        }),
        'permission_checks' => [
            'users.view' => $user->hasPermission('users.view'),
            'users.create' => $user->hasPermission('users.create'),
            'blog.view' => $user->hasPermission('blog.view'),
            'blog.create' => $user->hasPermission('blog.create'),
            'najm-hoda.view' => $user->hasPermission('najm-hoda.view'),
        ],
        'role_checks' => [
            'super-admin' => $user->hasRole('super-admin'),
            'user-manager' => $user->hasRole('user-manager'),
            'content-manager' => $user->hasRole('content-manager'),
        ],
    ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
})->middleware('auth')->name('test.rbac');

// پنل ادمین - گزارش‌ها
Route::middleware([AdminMiddleware::class, 'permission:reports.view'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/reports', [\App\Http\Controllers\Admin\ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{id}', [\App\Http\Controllers\Admin\ReportController::class, 'show'])->name('reports.show');
    
    Route::middleware('permission:reports.manage')->group(function () {
        Route::put('/reports/{id}', [\App\Http\Controllers\Admin\ReportController::class, 'update'])->name('reports.update');
        Route::post('/reports/bulk-action', [\App\Http\Controllers\Admin\ReportController::class, 'bulkAction'])->name('reports.bulk-action');
        Route::delete('/reports/{id}', [\App\Http\Controllers\Admin\ReportController::class, 'destroy'])->name('reports.destroy');
    });
});

// مدیریت سهام و حراج - ادمین
Route::middleware(AdminMiddleware::class)->prefix('admin')->name('admin.')->group(function () {
    // مدیریت سهام
    Route::middleware('permission:stock.view-dashboard')->group(function () {
        Route::get('stock', [\App\Modules\Stock\Controllers\StockController::class, 'adminIndex'])->name('stock.index');
        Route::get('stock/shareholders', [\App\Modules\Stock\Controllers\StockController::class, 'shareholders'])->name('stock.shareholders');
        
        Route::middleware('permission:stock.edit')->group(function () {
            Route::get('stock/create', [\App\Modules\Stock\Controllers\StockController::class, 'adminCreate'])->name('stock.create');
            Route::post('stock', [\App\Modules\Stock\Controllers\StockController::class, 'adminStore'])->name('stock.store');
            Route::get('stock/gift', [\App\Modules\Stock\Controllers\StockController::class, 'showGiftForm'])->name('stock.gift');
            Route::post('stock/gift', [\App\Modules\Stock\Controllers\StockController::class, 'giftShares'])->name('stock.gift.store');
        });
    });

    // مدیریت حراج‌ها
    Route::middleware('permission:stock.view-dashboard')->prefix('auctions')->name('auction.')->group(function () {
        Route::get('/', [\App\Modules\Stock\Controllers\AuctionController::class, 'adminIndex'])->name('index');
        
        Route::middleware('permission:stock.create')->group(function () {
            Route::get('/create', [\App\Modules\Stock\Controllers\AuctionController::class, 'adminCreate'])->name('create');
            Route::post('/', [\App\Modules\Stock\Controllers\AuctionController::class, 'adminStore'])->name('store');
        });
        
        Route::middleware('permission:stock.edit')->group(function () {
            Route::get('/{auction}/edit', [\App\Modules\Stock\Controllers\AuctionController::class, 'adminEdit'])->name('edit');
            Route::put('/{auction}', [\App\Modules\Stock\Controllers\AuctionController::class, 'adminUpdate'])->name('update');
            Route::post('/{auction}/start', [\App\Modules\Stock\Controllers\AuctionController::class, 'startAuction'])->name('start');
            Route::post('/{auction}/close', [\App\Modules\Stock\Controllers\AuctionController::class, 'closeAuction'])->name('close');
            Route::post('/{auction}/manual-settle', [\App\Modules\Stock\Controllers\AuctionController::class, 'manualSettleAuction'])->name('manual-settle');
            Route::post('/bulk-action', [\App\Modules\Stock\Controllers\AuctionController::class, 'bulkAction'])->name('bulk-action');
        });
        
        Route::middleware('permission:stock.delete')->group(function () {
            Route::delete('/{auction}', [\App\Modules\Stock\Controllers\AuctionController::class, 'adminDestroy'])->name('destroy');
        });
        
        Route::get('/{auction}', [\App\Modules\Stock\Controllers\AuctionController::class, 'adminShow'])->name('show');
        Route::get('/{auction}/export', [\App\Modules\Stock\Controllers\AuctionController::class, 'export'])->name('export');
    });
    
    // مدیریت کیف پول‌ها
    Route::middleware('permission:stock.view-dashboard')->prefix('wallet')->name('wallet.')->group(function () {
        Route::get('/', [\App\Modules\Stock\Controllers\WalletController::class, 'adminIndex'])->name('index');
        Route::get('/{wallet}', [\App\Modules\Stock\Controllers\WalletController::class, 'adminShow'])->name('show');
        
        Route::middleware('permission:stock.edit')->group(function () {
            Route::post('/credit', [\App\Modules\Stock\Controllers\WalletController::class, 'adminCredit'])->name('credit');
            Route::post('/debit', [\App\Modules\Stock\Controllers\WalletController::class, 'adminDebit'])->name('debit');
        });
    });
    
    // مدیریت holdings
    Route::middleware('permission:stock.view-dashboard')->prefix('holdings')->name('holdings.')->group(function () {
        Route::get('/', [\App\Modules\Stock\Controllers\HoldingController::class, 'adminIndex'])->name('index');
        Route::get('/{holding}', [\App\Modules\Stock\Controllers\HoldingController::class, 'adminShow'])->name('show');
    });
    
    // گزارش‌گیری
    Route::middleware('permission:stock.view-dashboard')->prefix('stock-reports')->name('stock-reports.')->group(function () {
        Route::get('/auction-performance', [\App\Modules\Stock\Controllers\StockReportController::class, 'auctionPerformance'])->name('auction-performance');
        Route::get('/investors', [\App\Modules\Stock\Controllers\StockReportController::class, 'investors'])->name('investors');
        Route::get('/financial', [\App\Modules\Stock\Controllers\StockReportController::class, 'financial'])->name('financial');
        
        Route::get('/auction-performance/export', [\App\Modules\Stock\Controllers\StockReportController::class, 'exportAuctionPerformance'])->name('export-auction-performance');
        Route::get('/investors/export', [\App\Modules\Stock\Controllers\StockReportController::class, 'exportInvestors'])->name('export-investors');
        Route::get('/financial/export', [\App\Modules\Stock\Controllers\StockReportController::class, 'exportFinancial'])->name('export-financial');
    });
});


Route::get('/fields/children/{id}', function ($id) {
    return \App\Models\OccupationalField::where('parent_id', $id)->get(['id', 'name']);
});


Route::get('/fields/children-ex/{id}', function ($id) {
    return \App\Models\ExperienceField::where('parent_id', $id)->get(['id', 'name']);
});

// Email Verification Routes
Route::post('/email/verify/send', [EmailVerificationController::class, 'sendVerificationCode'])->name('email.verification.send');
Route::get('/email/verify', [EmailVerificationController::class, 'showVerificationForm'])->name('email.verify.form');
Route::post('/email/verify', [EmailVerificationController::class, 'verify'])->name('email.verify');
Route::post('/email/verify/resend', [EmailVerificationController::class, 'resend'])->name('email.verification.resend');


Route::middleware(Authenticate::class)->group(function () {
    Route::get('/chat-requests', [ChatRequestController::class, 'index'])->name('chat-requests.index');
    Route::get('/chat-requests/pending-count', [ChatRequestController::class, 'pendingCount'])->name('chat-requests.pending-count');
    Route::get('/chat-requests/pending', [ChatRequestController::class, 'pending'])->name('chat-requests.pending');
    Route::post('/chat-requests/{user}', [ChatRequestController::class, 'send'])->name('chat-requests.send');
    Route::post('/chat-requests/{chatRequest}/accept', [ChatRequestController::class, 'accept'])->name('chat-requests.accept');
    Route::post('/chat-requests/{chatRequest}/reject', [ChatRequestController::class, 'reject'])->name('chat-requests.reject');
    
    // Private Chat routes
    Route::get('/private-chats/{conversation}', [PrivateChatController::class, 'show'])->name('private-chats.show');
    Route::post('/private-chats/{conversation}/messages', [PrivateChatController::class, 'sendMessage'])->name('private-chats.send');
    
    // Private Chat API endpoints for AJAX/Real-time
    Route::get('/private-chats/{conversation}/messages', [PrivateChatController::class, 'getMessages'])->name('private-chats.messages');
    Route::get('/private-chats/{conversation}/info', [PrivateChatController::class, 'getConversationInfo'])->name('private-chats.info');
    
    // Message Reaction routes
    Route::post('/messages/reactions', [MessageReactionController::class, 'store'])->name('messages.reactions.store');
    Route::delete('/messages/reactions', [MessageReactionController::class, 'destroy'])->name('messages.reactions.destroy');
    Route::get('/messages/reactions', [MessageReactionController::class, 'index'])->name('messages.reactions.index');
    
    // Message Report routes
    Route::post('/private-chats/messages/report', [PrivateChatReportController::class, 'store'])->name('private-chats.report');

    Route::middleware('admin')->group(function () {
        Route::get('/admin/private-chat-reports', [PrivateChatReportController::class, 'index'])->name('admin.private-chat-reports');
        Route::get('/admin/private-chat-reports/{id}', [PrivateChatReportController::class, 'show'])->name('admin.private-chat-reports.show');
        Route::post('/admin/private-chat-reports/{id}/review', [PrivateChatReportController::class, 'review'])->name('admin.private-chat-reports.review');
        Route::delete('/admin/private-chat-reports/{id}', [PrivateChatReportController::class, 'destroy'])->name('admin.private-chat-reports.destroy');
    });
    
    Route::get('/chat/{group}', [ChatController::class, 'show'])->name('chat.show');
});



Route::get('/api/groups/search', function (Request $request) {
    if (!auth()->check()) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $searchText = $request->query('q');
    $searchType = $request->query('type', 'name');
    
    if (empty($searchText)) {
        return response()->json(['groups' => []]);
    }
    
    $query = \App\Models\Group::query()
        ->whereHas('users', function ($query) {
            $query->where('user_id', auth()->id())
                  ; 
        })
        ->with(['users' => function ($query) {
            $query->where('user_id', auth()->id());
        }]);
    
    if ($searchType === 'content') {
        $query->where(function ($q) use ($searchText) {
            $q->whereHas('messages', function ($q) use ($searchText) {
                $q->where('message', 'like', "%{$searchText}%");
            });
        })->orWhere(function ($q) use ($searchText) {
            $q->whereHas('blogs', function ($q) use ($searchText) {
                $q->where('title', 'like', "%{$searchText}%");
            });
        })->orWhere(function ($q) use ($searchText) {
            $q->whereHas('polls', function ($q) use ($searchText) {
                $q->where('question', 'like', "%{$searchText}%");
            });
        });
    } else {
        $query->where('name', 'like', "%{$searchText}%");
    }
    
    $groups = $query->get()->map(function ($group) use ($searchText) {
        $pivot = $group->users->first()->pivot;
        
        // Get all matching messages for this group
        $matchingMessages = $group->messages()
            ->where('message', 'like', "%{$searchText}%")
            ->with('user')
            ->get();
        
        return [
            'id' => $group->id,
            'name' => $group->name,
            'avatar' => $group->avatar ? asset('images/groups/' . $group->avatar) : null,
            'members_count' => $group->users->count(),
            'location_level' => $group->location_level,
            'is_approved' => 1,
            'status' => 1,
            'role' => match($pivot->role) {
                0 => 'ناظر',
                1 => 'فعال',
                2 => 'بازرس',
                3 => 'مدیر',
            },
            'matching_messages' => $matchingMessages->map(function($message) {
                return [
                    'id' => $message->id,
                    'message' => $message->message,
                    'created_at' => $message->created_at,
                    'user' => [
                        'id' => $message->user->id,
                        'name' => $message->user->name
                    ]
                ];
            })
        ];
    });
    
    return response()->json(['groups' => $groups]);
});

Route::prefix('api')->group(function () {
    // Chat features routes
    Route::post('/groups/{group}/clear-history', [ChatController::class, 'clearHistory']);
    Route::post('/groups/{group}/delete', [ChatController::class, 'deleteChat']);
    Route::post('/groups/{group}/report', [ChatController::class, 'reportUser']);
});


Route::get('/api/groups/{group}/content', function (App\Models\Group $group) {
    $content = [];
    
    // Get latest blog posts
    $blogs = $group->blogs()->latest()->take(5)->get();
    foreach ($blogs as $blog) {
        $content[] = $blog->title;
        $content[] = $blog->content;
    }
    
    // Get latest comments
    $comments = $group->blogs()->with('comments')->get()->pluck('comments')->flatten()->take(5);
    foreach ($comments as $comment) {
        $content[] = $comment->message;
    }
    
    // Get latest polls
    $polls = $group->polls()->latest()->take(5)->get();
    foreach ($polls as $poll) {
        $content[] = $poll->title;
        $content[] = $poll->description;
    }
    
    return response()->json([
        'content' => implode(' ', $content)
    ]);
});


Route::get('/users/search', function(Request $request){
    $query = $request->get('q');

    $users = \App\Models\User::where('first_name', 'like', "%{$query}%")
        ->orWhere('last_name', 'like', "%{$query}%")
        ->orWhere('email', 'like', "%{$query}%")
        ->orWhere('phone', 'like', "%{$query}%")
        ->get(['id', 'first_name','last_name', 'email']);

    return response()->json($users);
});

Route::get('/categories/{category}/blogs', function(\App\Models\Category $category, Request $request){
    $query = Blog::query()
            ->where('category_id', $category->id)
            ->latest();

        if ($request->filled('group_id')) {
            $query->where('group_id', $request->integer('group_id'));
        }

        $blogs = $query->select(['id','title','created_at'])->get();

        // این Route رو با چیزی که تو پروژه‌ات برای نمایش صفحه‌ی بلاگ داری هماهنگ کن
        // طبق regex موجود، ظاهراً مسیر نمایش بلاگ: /groups/comment/{id}
        $items = $blogs->map(function ($b) {
            return [
                'id' => $b->id,
                'title' => $b->title,
                'date' => verta($b->created_at)->format('Y/m/d H:i'),
                'url' => route('groups.comment', $b->id), // اگر اسمت فرق دارد، همین‌جا عوض کن
            ];
        });

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
            ],
            'count' => $blogs->count(),
            'items' => $items,
        ]);
})
    ->name('categories.blogs');
    
    
    
    
    
    Route::post('profile/add-{type}', function(Request $request, $type) {
    $allowed = ['region','neighborhood','street','alley'];
    if (!in_array($type, $allowed, true)) {
        return response()->json(['message' => 'نوع نامعتبر است'], 422);
    }

    // trim name
    $request->merge(['name' => trim((string)$request->input('name'))]);

$request->validate([
  'name' => 'required|string|max:255',
  'parent_id' => ['required'],
], [], [
  'name' => 'نام',
  'parent_id' => 'شناسه والد',
]);


$rawParent = (string) $request->input('parent_id');

$kind = null;
$pid  = null;
$allowedKinds = ['city','rural','region','neighborhood','street','village'];

$raw = trim(mb_strtolower($rawParent));

// حالت فقط عدد
if ($raw !== '' && ctype_digit($raw)) {
    $pid = (int) $raw;
} else {
    // شکستن بر اساس _
    $parts = array_values(array_filter(explode('_', $raw), fn($p) => $p !== ''));

    // مثال‌ها:
    // city_city_52 → ['city','city','52']
    // region_77    → ['region','77']
    // neighborhood___15 → ['neighborhood','15']
    if (count($parts) >= 2) {
        $first = $parts[0];                          // نوع را از اولین بخش می‌گیریم
        $last  = $parts[count($parts) - 1];          // شناسه را از آخرین بخش می‌گیریم

        if (in_array($first, $allowedKinds, true) && ctype_digit($last)) {
            $kind = $first;
            $pid  = (int) $last;
        }
    }
}

if ($pid === null) {
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
                        // ⬅ اگر Region ستون city_id دارد، از آن استفاده کن
                        return Region::create([
                            'name'     => $name,
                            'city_id'  => $pid,   // ⬅ اگر در اسکیمای تو 'parent_id' است، این خط را به 'parent_id' تغییر بده
                            'status'   => 0,
                        ]);
                    }

                case 'neighborhood':

                    return Neighborhood::create([
                        'name'      => $name,
                        'parent_id' => $pid,   // ⬅ اگر ستونت 'region_id' است، همین‌جا تغییر بده
                        'status'    => 0,
                    ]);

                case 'street':

                    return Street::create([
                        'name'      => $name,
                        'parent_id' => $pid,   // ⬅ در صورت تفاوت اسکیمای جدول streets اینجا را تغییر بده
                        'status'    => 0,
                    ]);

                case 'alley':

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

/*
|--------------------------------------------------------------------------
| Blog Module Routes
|--------------------------------------------------------------------------
*/

// Public Blog Routes
Route::prefix('blog')->name('blog.')->group(function () {
    Route::get('/', [ModuleBlogController::class, 'index'])->name('index');
    Route::get('/search', [ModuleBlogController::class, 'search'])->name('search');
    Route::get('/category/{slug}', [ModuleBlogController::class, 'category'])->name('category');
    Route::get('/tag/{slug}', [ModuleBlogController::class, 'tag'])->name('tag');
    Route::get('/{slug}', [ModuleBlogController::class, 'show'])->name('show');
    
    // Comments (requires auth)
    Route::middleware(['auth'])->group(function () {
        Route::post('/{post}/comment', [ModuleBlogController::class, 'storeComment'])->name('comment.store');
    });
});

// Admin Blog Routes
Route::middleware([\App\Http\Middleware\AdminMiddleware::class])->prefix('admin/blog')->name('admin.blog.')->group(function () {
    // Dashboard
    Route::middleware('permission:blog.view-dashboard')->get('/dashboard', [AdminBlogController::class, 'dashboard'])->name('dashboard');
    
    // Posts Management
    Route::middleware('permission:blog.view-posts')->group(function () {
        Route::get('/posts', [AdminBlogController::class, 'posts'])->name('posts');
        Route::get('/posts/{post}/edit', [AdminBlogController::class, 'editPost'])->name('posts.edit');
    });
    
    Route::middleware('permission:blog.create-posts')->group(function () {
        Route::get('/posts/create', [AdminBlogController::class, 'createPost'])->name('posts.create');
        Route::post('/posts', [AdminBlogController::class, 'storePost'])->name('posts.store');
    });
    
    Route::middleware('permission:blog.edit-posts')->group(function () {
        Route::put('/posts/{post}', [AdminBlogController::class, 'updatePost'])->name('posts.update');
    });
    
    Route::middleware('permission:blog.delete-posts')->group(function () {
        Route::delete('/posts/{post}', [AdminBlogController::class, 'deletePost'])->name('posts.delete');
    });
    
    // Categories Management
    Route::middleware('permission:blog.manage-categories')->group(function () {
        Route::get('/categories', [AdminBlogController::class, 'categories'])->name('categories');
        Route::get('/categories/create', [AdminBlogController::class, 'createCategory'])->name('categories.create');
        Route::post('/categories', [AdminBlogController::class, 'storeCategory'])->name('categories.store');
        Route::get('/categories/{category}/edit', [AdminBlogController::class, 'editCategory'])->name('categories.edit');
        Route::put('/categories/{category}', [AdminBlogController::class, 'updateCategory'])->name('categories.update');
        Route::delete('/categories/{category}', [AdminBlogController::class, 'deleteCategory'])->name('categories.delete');
    });
    
    // Tags Management
    Route::middleware('permission:blog.manage-tags')->group(function () {
        Route::get('/tags', [AdminBlogController::class, 'tags'])->name('tags');
        Route::post('/tags', [AdminBlogController::class, 'storeTag'])->name('tags.store');
        Route::put('/tags/{tag}', [AdminBlogController::class, 'updateTag'])->name('tags.update');
        Route::delete('/tags/{tag}', [AdminBlogController::class, 'deleteTag'])->name('tags.delete');
    });
    
    // Comments Management
    Route::middleware('permission:blog.manage-comments')->group(function () {
        Route::get('/comments', [AdminBlogController::class, 'comments'])->name('comments');
        Route::post('/comments/{comment}/approve', [AdminBlogController::class, 'approveComment'])->name('comments.approve');
        Route::post('/comments/{comment}/reject', [AdminBlogController::class, 'rejectComment'])->name('comments.reject');
        Route::delete('/comments/{comment}', [AdminBlogController::class, 'deleteComment'])->name('comments.delete');
    });
});

// Knowledge Base (KB) Admin Routes
Route::middleware([\App\Http\Middleware\AdminMiddleware::class])->prefix('admin/kb')->name('admin.kb.')->group(function () {
    // Articles
    Route::middleware('permission:kb.view')->group(function () {
        Route::get('/articles', [\App\Http\Controllers\Admin\KbArticleController::class, 'index'])->name('articles.index');
        Route::get('/articles/create', [\App\Http\Controllers\Admin\KbArticleController::class, 'create'])->name('articles.create');
        Route::post('/articles', [\App\Http\Controllers\Admin\KbArticleController::class, 'store'])->name('articles.store');
        Route::get('/articles/{article}/edit', [\App\Http\Controllers\Admin\KbArticleController::class, 'edit'])->name('articles.edit');
        Route::put('/articles/{article}', [\App\Http\Controllers\Admin\KbArticleController::class, 'update'])->name('articles.update');
        Route::post('/articles/{article}/toggle', [\App\Http\Controllers\Admin\KbArticleController::class, 'toggleStatus'])->name('articles.toggle');
        Route::delete('/articles/{article}', [\App\Http\Controllers\Admin\KbArticleController::class, 'destroy'])->name('articles.destroy');
    });

    // Categories
    Route::middleware('permission:kb.manage')->group(function () {
        Route::get('/categories', [\App\Http\Controllers\Admin\KbCategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [\App\Http\Controllers\Admin\KbCategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [\App\Http\Controllers\Admin\KbCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [\App\Http\Controllers\Admin\KbCategoryController::class, 'destroy'])->name('categories.destroy');
    });

    // Tags
    Route::middleware('permission:kb.manage')->group(function () {
        Route::get('/tags', [\App\Http\Controllers\Admin\KbTagController::class, 'index'])->name('tags.index');
        Route::post('/tags', [\App\Http\Controllers\Admin\KbTagController::class, 'store'])->name('tags.store');
        Route::put('/tags/{tag}', [\App\Http\Controllers\Admin\KbTagController::class, 'update'])->name('tags.update');
        Route::delete('/tags/{tag}', [\App\Http\Controllers\Admin\KbTagController::class, 'destroy'])->name('tags.destroy');
    });

    // Steward Dashboard (Monitoring)
    Route::get('/steward-dashboard', fn() => view('steward-dashboard'))->name('steward.dashboard');
});

