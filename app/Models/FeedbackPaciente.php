<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedbackPaciente extends Model
{
    protected $table = 'feedback_pacientes';

    protected $fillable = [
        'os_numero',
        'nota',
        'comentario',
        'ip',
        'user_agent'
    ];
}
