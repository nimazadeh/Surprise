<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlugRedirect extends Model
{
    public const SUBJECT_ARTIST = 'artist';

    public const SUBJECT_ALBUM = 'album';

    public const SUBJECT_TRACK = 'track';

    public const SUBJECT_GENRE = 'genre';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subject_type',
        'old_slug',
        'new_slug',
    ];
}
