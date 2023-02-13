<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedXml extends Model
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';

    use HasFactory;

    protected $fillable = [
        'source',
        'platform',
        'status',
        'xml'
    ];

    protected ?string $source;
    protected ?string $platform;
    protected ?string $status;
    protected ?string $xml;
}
