<?php

namespace Enjoin;

use Exception;

class NonPersistentRecord
{

    /**
     * @param Record $Record
     * @param array $attributes
     * @return Record
     * @throws \Exception
     */
    public static function save(Record &$Record, array $attributes = [])
    {
        /**
         * @var Model $Model
         */
        $Model = $Record->_getInternal('model');

        # Collect values
        $values = Extras::omit(get_object_vars($Record), $Record->_getInternal('omit'));
        if (!$values) {
            throw new Exception('Expected non-empty attributes list');
        }
        if (!$attributes) {
            $attributes = array_keys($values);
        }

        $skip = [];

        # Perform timestamps
        if ($Model->isTimestamps()) {
            ## Created at
            $created_at = $Model->getCreatedAtAttr();
            $insert[$created_at] = Setters::getCreatedAt(null, true);
            $skip [] = $created_at;
            ## Updated at
            $updated_at = $Model->getUpdatedAtAttr();
            $update[$updated_at] = Setters::getUpdatedAt();
            $skip [] = $updated_at;
        }

        # Perform setters
        $contextAttrs = $Model->Context->getAttributes();
        $insert = [];
        foreach ($attributes as $attr) {
            if (!array_key_exists($attr, $values) || in_array($attr, $skip)) {
                continue;
            }
            $insert[$attr] = Setters::perform($attr, $contextAttrs[$attr], $values);
            # Perform validation
            if (array_key_exists('validate', $contextAttrs[$attr])) {
                PersistentRecord::validate($attr, $insert[$attr], $contextAttrs[$attr]['validate']);
            }
        }

        # Insert entry
        $Model->flushCache();
        $id = $Model->connect()->insertGetId($insert);

        # Apply entry properties
        $entry = $Model->find($id);
        foreach (get_object_vars($entry) as $k => $v) {
            if (!in_array($k, $Record->_getInternal('omit'))) {
                $Record->$k = $v;
            }
        }

        # Set record `id`
        $Record->_setInternal('id', $id);

        # Change record type
        $Record->_setInternal('type', Extras::$PERSISTENT_RECORD);

        return $Record;
    }

    /**
     * @param Record $Record
     * @return bool
     */
    public static function destroy(Record &$Record)
    {
        $Record->_setInternal('type', null);
        return true;
    }

} // end of class
