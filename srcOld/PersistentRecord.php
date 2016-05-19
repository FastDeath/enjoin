<?php

namespace Enjoin;

use Carbon\Carbon;
use Exception, Validator;

class PersistentRecord
{

    /**
     * @param Record $Record
     * @param array $attributes
     * @return bool
     * @throws \Exception
     */
    public static function save(Record $Record, array $attributes = [])
    {
        /**
         * @var Model $Model
         */
        $Model = $Record->_getInternal('model');
        $contextAttrs = $Model->Context->getAttributes();

        # Collect values
        $values = Extras::omit(get_object_vars($Record), Extras::$RECORD_OMIT);
        if (!$values) {
            throw new Exception('Expected non-empty attributes list');
        }
        if (!$attributes) {
            $attributes = array_keys($values);
            if (($id_idx = array_search('id', $attributes)) !== false) {
                unset($attributes[$id_idx]);
            }
        }
        # Filter attributes by model context.
        $attributes = array_filter($attributes, function ($attr) use ($contextAttrs) {
            return array_key_exists($attr, $contextAttrs);
        });

        $update = [];
        $skip = [];

        # Perform timestamps
        if ($Model->isTimestamps()) {
            ## Created at
            $created_at = $Model->getCreatedAtAttr();
            if (array_key_exists($created_at, $values)) {
                $update[$created_at] = Setters::getCreatedAt($values[$created_at]);
            }
            $skip [] = $created_at;
            ## Updated at
            $updated_at = $Model->getUpdatedAtAttr();
            $update[$updated_at] = Setters::getUpdatedAt();
            $skip [] = $updated_at;
            $Record->$updated_at = self::touchUpdatedAt(
                (isset($Record->$updated_at) ? $Record->$updated_at : null)
            );
        }

        # Perform setters
        foreach ($attributes as $attr) {
            if (!array_key_exists($attr, $values) || in_array($attr, $skip)) {
                continue;
            }
            $update[$attr] = Setters::perform($attr, $contextAttrs[$attr], $values);
            # Perform validation
            if (array_key_exists('validate', $contextAttrs[$attr])) {
                self::validate($attr, $update[$attr], $contextAttrs[$attr]['validate']);
            }
        }

        # Update entry
        $Model->CC->flush();
        $Model->connect()->where('id', $Record->_getInternal('id'))->take(1)->update($update);

        return true;
    }

    /**
     * @param $value
     * @return string|static
     */
    public static function touchUpdatedAt($value)
    {
        if ($value instanceof Carbon) {
            return Carbon::now();
        }
        return Carbon::now()->toDateTimeString();
    }

    /**
     * @param $attr
     * @param $value
     * @param $rules
     * @throws \Exception
     */
    public static function validate($attr, $value, $rules)
    {
        $validator = Validator::make([$attr => $value], [$attr => $rules]);
        if ($validator->fails()) {
            $messages = [];
            foreach ($validator->messages()->get($attr) as $msg) {
                $messages [] = $msg;
            }
            throw new Exception(implode("\n", $messages));
        }
    }

    /**
     * @param Record $Record
     * @return bool
     */
    public static function destroy(Record $Record)
    {
        /**
         * @var Model $Model
         */
        $Model = $Record->_getInternal('model');

        $Model->CC->flush();
        $Model->connect()->where('id', $Record->_getInternal('id'))->take(1)->delete();
        $Record->_setInternal('type', null);
        return true;
    }

} // end of class
