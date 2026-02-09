<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\Poll;
use App\Models\PollOption;
use Carbon\Carbon;

class PollController extends Controller
{
    public function store(Group $group, Request $request){
        $inputs = $request->validate([
            'question'   => 'required|string|max:255',            
            'options' => 'required|array', 
            'expires_at' => 'required|numeric',
            'type' => 'required|numeric|in:0,1',
            'skill_id' => 'nullable',
            'main_type' => 'required|numeric|in:0,1',
        ]);
        $inputs['expires_at'] = Carbon::now()->addDays($inputs['expires_at'])->format('Y-m-d H:i:s');

        $inputs['group_id'] = $group->id;
        $inputs['created_by'] = auth()->user()->id;

        $poll = Poll::create($inputs);
        $poll->refresh(); // برای اطمینان از بارگذاری روابط

        foreach($inputs['options'] as $option){
            PollOption::create([
                'poll_id' => $poll->id,
                'text' => $option,
            ]);
        }

        // Dispatch event for notifications
        event(new \App\Events\PollCreated($poll, $group, auth()->user()));
    
        return redirect()->back()->with('success', 'نظرسنجی شما با موفقیت ارسال شد!');
    }

// PollController
public function vote(Request $request, Poll $poll)
{
    $request->validate([
        'option_id' => 'required|exists:poll_options,id'
    ]);

    if ($poll->votes()->where('user_id', auth()->id())->exists()) {
        $poll->votes()->where('user_id', auth()->id())->first()->delete();
    }

    $poll->votes()->create([
        'user_id' => auth()->id(),
        'option_id' => $request->option_id,
    ]);

    return response()->json(['status' => 'success']);
}

public function update(Request $request, Group $group, Poll $poll)
{
    $poll->update([
        'question' => $request->question,
        'expires_at' => now()->addDays($request->expires_at),
        'real_type' => $request->type,
        'skill_id' => $request->type == 1 ? $request->skill_id : null,
    ]);

    return redirect()->back()->with('success', 'نظرسنجی با موفقیت ویرایش شد.');
}



public function delete(Group $group, Poll $poll)
{
    $poll->delete();

    return redirect()->back()->with('success', 'نظرسنجی با موفقیت حذف شد.');
}


}
