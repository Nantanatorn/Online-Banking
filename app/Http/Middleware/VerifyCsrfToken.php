<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * URIs ที่ไม่ต้องใช้ CSRF
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/*' // ✅ ยกเว้น API ทั้งหมดจาก CSRF
    ];
}
