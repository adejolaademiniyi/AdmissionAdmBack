<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class Applicant extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'gender',
        'phone_number',
        'email',
        'password',
        'passport_path',
        'home_address',
        'state',
        'local_government',
        'application_number',
        'status',
    ];

    protected $hidden = ['password', 'passport_path'];

    protected $appends = ['passport_url'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    protected function passportUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->passport_path
                ? Storage::disk('public')->url($this->passport_path)
                : null
        );
    }

    /**
     * Return the passport photo as a base64 data URI.
     *
     * Used for print/PDF rendering: a data URI is same-origin, so it is
     * never blocked by CORS and never taints a canvas, which guarantees
     * the photo appears on the printed application form.
     */
    public function passportDataUri(): ?string
    {
        if (! $this->passport_path || ! Storage::disk('public')->exists($this->passport_path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($this->passport_path) ?: 'image/jpeg';
        $data = base64_encode(Storage::disk('public')->get($this->passport_path));

        return "data:{$mime};base64,{$data}";
    }

    // Valid status values
    public const STATUSES = ['pending', 'under_review', 'approved', 'rejected'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Applicant $applicant) {
            $applicant->application_number = 'APP-' . strtoupper(uniqid());
        });
    }

    public function nextOfKin(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(NextOfKin::class);
    }

    public function healthRecord(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(HealthRecord::class);
    }
}
