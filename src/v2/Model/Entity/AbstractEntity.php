<?php
declare(strict_types=1);
namespace HB9AKM\API\V2\Model\Entity;

abstract class AbstractEntity {

    public static function getFieldDefinitions(): array {
        $main = \HB9AKM\API\Main::getInstance();
        $em = $main->getEm();
        $meta = $em->getClassMetadata(static::class);
        $fieldDefs = array();

        foreach ($meta->getFieldNames() as $fieldname) {
            // TODO: Indication translatable
            // TODO: Add translations of field labels
            $fieldMapping = $meta->getFieldMapping($fieldname);
            $fieldDefs[$fieldname] = array(
                'type' => $fieldMapping['type'],
                //'length' => '',
                'primary' => (isset($fieldMapping['id']) && $fieldMapping['id']),
                //'unique' => '',
                //'nullable' => '',
            );
        }
        return $fieldDefs;
    }

    public function toArray(): array {
        $main = \HB9AKM\API\Main::getInstance();
        $em = $main->getEm();
        $meta = $em->getClassMetadata(get_class($this));
        $data = array();
        $repository = $em->getRepository('HB9AKM\API\V2\Model\Entity\Translation');
        $translations = $repository->findTranslations($this);
        $firstTranslation = current($translations);
        foreach ($meta->getFieldNames() as $fieldname) {
            $fieldvalue = $meta->getFieldValue($this, $fieldname);
            // TODO: This should be triggered based on if the field is translatable
            if (count($translations) && isset($firstTranslation[$fieldname])) {
                $fieldvalue = array('en-US' => $fieldvalue);
                foreach ($translations as $locale=>$fields) {
                    $fieldvalue[$locale] = $fields[$fieldname];
                }
            }
            $data[$fieldname] = $fieldvalue;
        }
        return $data;
    }
}

