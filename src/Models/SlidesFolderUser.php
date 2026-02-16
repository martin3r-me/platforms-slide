<?php

namespace Platform\Slides\Models;

use Illuminate\Database\Eloquent\Model;

class SlidesFolderUser extends Model
{
    protected $table = 'slides_folder_users';

    protected $fillable = ['folder_id', 'role', 'user_id'];

    public function user()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function folder()
    {
        return $this->belongsTo(SlidesFolder::class, 'folder_id');
    }
}
