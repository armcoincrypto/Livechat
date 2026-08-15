<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;

class TranslatableObserver
{
    /**
     * Removes empty strings in translations before saving model
     */
    public function saving(Model $modelInstance)
    {
        $translations = $modelInstance->getTranslations();
        foreach ($translations as $key => $values) {
            foreach ($values as $locale => $value) {
                if (empty($value)) {
                    $modelInstance->forgetTranslation($key, $locale);
                }
            }
        }
    }
}
