<?php

namespace App\Services;

use App\Events\ImageUpdated;

trait FireImageUpdatedEvent
{
    public static function bootFireImageUpdatedEvent(): void
    {
        static::updating(function ($model) {
            // Direct single-image field
            if ($model->isDirty('image')) {
                event(new ImageUpdated(
                    $model,
                    $model->getOriginal('image'),
                ));
            }

            // Direct multiple-image field
            if ($model->isDirty('attachments')) {
                $old = $model->getOriginal('attachments') ?: [];
                $new = $model->attachments ?: [];

                $removed = array_diff($old, $new);

                event(new ImageUpdated(
                    $model,
                    $removed ?: $old,
                ));
            }

            // Check for logo or favicon in value field
            if ($model->isDirty('value') && in_array($model->key, ['site.logo'])) {
                $old = $model->getOriginal('value');
                $new = $model->value;

                if ($old !== $new) {
                    event(new ImageUpdated(
                        $model,
                        [$old], // Send old image path for deletion
                    ));
                }
            }
        });

        static::deleting(function ($model) {
            if (! empty($model->image)) {
                event(new ImageUpdated(
                    $model,
                    [$model->image],
                ));
            }

            if (! empty($model->attachments)) {
                event(new ImageUpdated(
                    $model,
                    $model->attachments,
                ));
            }

            // Handle logo or favicon on delete (value field)
            if (in_array($model->key ?? '', ['site.logo']) && ! empty($model->value)) {
                event(new ImageUpdated(
                    $model,
                    [$model->value],
                ));
            }
        });
    }

    protected function getImageFields(): array
    {
        return property_exists($this, 'imageFields')
            ? $this->imageFields
            : ['image', 'attachments'];
    }
}
