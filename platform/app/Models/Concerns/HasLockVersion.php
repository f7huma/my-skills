<?php

namespace App\Models\Concerns;

use App\Exceptions\StaleRecordException;

/**
 * قفل تفاؤلي: يرسل النموذج lock_version مع كل تعديل، ويُرفض الحفظ إن تغيّر السجل منذ تحميله.
 */
trait HasLockVersion
{
    public function initializeHasLockVersion(): void
    {
        if (! array_key_exists('lock_version', $this->attributes)) {
            $this->attributes['lock_version'] = 1;
        }
    }

    public static function bootHasLockVersion(): void
    {
        static::updating(function ($model) {
            if ($model->isDirty() && ! $model->isDirty('lock_version')) {
                $model->lock_version = ($model->getOriginal('lock_version') ?? 0) + 1;
            }
        });
    }

    public function assertLockVersion(?int $expected): void
    {
        if ($expected !== null && (int) $this->lock_version !== $expected) {
            throw new StaleRecordException;
        }
    }

    /** تحديث ذري مشروط بالإصدار — يمنع الكتابة فوق تعديل متزامن حتى بين طلبين متوازيين */
    public function saveWithVersion(?int $expected): void
    {
        if ($expected === null) {
            $this->save();

            return;
        }
        $this->assertLockVersion($expected);
        $dirty = $this->getDirty();
        if ($dirty === []) {
            return;
        }
        $dirty['lock_version'] = $expected + 1;
        $dirty['updated_at'] = now();
        $affected = static::query()->whereKey($this->getKey())->where('lock_version', $expected)->update($dirty);
        if ($affected === 0) {
            throw new StaleRecordException;
        }
        $this->forceFill($dirty)->syncOriginal();
    }
}
