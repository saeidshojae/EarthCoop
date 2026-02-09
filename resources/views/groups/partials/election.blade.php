    
    @php
    
        $checkBlockElection = \App\Models\Block::where('user_id', auth()->user()->id)->where('position', 'election')->first();
        
        $groupSetting =  \App\Models\GroupSetting::where('level', $group->location_level)->first();

    @endphp

    @if($groupSetting->election_status == 1)
<div class="election-card" id="electionRedirect">
    <h4 style='display: flex; align-items: center; flex-direction: row-reverse;'>انتخابات هیات مدیره و بازرسان گروه <img style='width: 2rem; margin-left: .5rem' src='{{ asset('images/elections.png') }}'></h4><br>
    @if($checkBlockElection == null AND auth()->user()->status == 1)
    <input type="button" value="شرکت در انتخابات" class="btn btn-warning" style="width: 100%;     background-color: #0068ff;" onclick="openElectionBox()">
    @else
    <input type="button" value="شما مجاز به شرکت در انتخابات نیستید" class="btn btn-warning" style="width: 100%">
        
    @endif
</div>

@endif