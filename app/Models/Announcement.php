<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;
    protected $fillable = ['title', 'content', 'group_level', 'image', 'should_pin', 'created_by'];

    protected $casts = [
        'should_pin' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pinnedMessages()
    {
        return $this->hasMany(PinnedMessage::class, 'announcement_id');
    }

    public function groupLevel(){
        if($this->group_level == 0){
            return 'جهانی';
        }elseif($this->group_level == 1){
            return 'کشور';
        }elseif($this->group_level == 2){
            return 'استان';
        }elseif($this->group_level == 3){
            return 'شهرستان';
        }elseif($this->group_level == 4){
            return 'بخش';
        }elseif($this->group_level == 5){
            return 'شهر/روستا';
        }elseif($this->group_level == 6){
            return 'منطقه/دهستان';
        }elseif($this->group_level == 7){
            return 'محله';
        }elseif($this->group_level == 8){
            return 'خیابان';
        }elseif($this->group_level == 9){
            return 'کوچه';
        }
    }
}
