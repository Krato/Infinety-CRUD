<?php

namespace App\Listeners;

use Spatie\MediaLibrary\Events\MediaHasBeenAdded;

class MediaLogger
{
    /**
     * Handle the event.
     *
     * Saves url of asset to the model
     *
     * @param  Spatie MediaLibrary Events MediaHasBeenAdded  $event
     * @return void
     */
    public function handle(MediaHasBeenAdded $events)
    {
        foreach ($events as $event) {
            $model = app($event->model_type)->find($event->model_id);
            $image = $event->getUrl();
            $convert = $event->getCustomProperty('setModel', false);
            if ($convert) {
                $collection = $event->collection_name;
                if (isset($model->$collection)) {
                    $model->$collection = asset($image);
                    $model->save();
                }
            }
        }
    }
}
