<?php
namespace NOSQL\Services\Helpers;

use MongoDB\Driver\ReadPreference;
use NOSQL\Dto\CollectionDto;
use NOSQL\Services\Base\NOSQLBase;
use PSFS\base\dto\Field;
use PSFS\base\dto\Form;
use PSFS\base\types\helpers\ApiHelper;

/**
 * Class NOSQLApiHelper
 * @package NOSQL\Services\Helpers
 */
class NOSQLApiHelper extends ApiHelper {

    /**
     * @return Field
     * @throws \PSFS\base\exception\GeneratorException
     */
    private static function generateId() {
        $field = new Field('_id', t('Id'), Field::HIDDEN_TYPE);
        $field->readonly = true;
        $field->pk = true;
        return $field;
    }

    /**
     * @param CollectionDto $collectionDto
     * @return Form
     * @throws \PSFS\base\exception\GeneratorException
     * @throws \Exception
     */
    public static function parseForm(CollectionDto $collectionDto) {
        $form = new Form(false);
        $form->addField(self::generateId());
        foreach($collectionDto->properties as $property) {
            $values = null;
            $data = [];
            $url = null;
            switch ($property->type) {
                case NOSQLBase::NOSQL_TYPE_INTEGER:
                case NOSQLBase::NOSQL_TYPE_DOUBLE:
                case NOSQLBase::NOSQL_TYPE_LONG:
                    $type = Field::NUMBER_TYPE;
                    break;
                case NOSQLBase::NOSQL_TYPE_BOOLEAN:
                    $type = Field::SWITCH_TYPE;
                    break;
                case NOSQLBase::NOSQL_TYPE_ARRAY:
                    $type = Field::COMBO_TYPE;
                    break;
                case NOSQLBase::NOSQL_TYPE_DATE:
                    $type = Field::TIMESTAMP;
                    break;
                case NOSQLBase::NOSQL_TYPE_OBJECT:
                    $type = Field::TEXTAREA_TYPE;
                    break;
                case NOSQLBase::NOSQL_TYPE_ENUM:
                    $type = Field::COMBO_TYPE;
                    $enumValues = explode('|', $property->enum);
                    foreach($enumValues as $value) {
                        $data[] = [
                            $property->name => $value,
                            'Label' => t($value),
                        ];
                    };
                    break;
                default:
                    $type = Field::TEXT_TYPE;
                    break;
            }
            $field = new Field($property->name, $property->description ?: $property->name, $type, $values, $data, $url, $property->required);
            $field->pk = false;
            $form->addField($field);
        }
        return $form;
    }

	/**
	 * Method to avoid the warning for the deprecated constant ReadPreference::RP_PRIMARY
	 * @return array
	 */
	public static function getReadPreferenceOptions()
	{
		$options = [];
		if (defined('MongoDB\Driver\ReadPreference::PRIMARY')) {
			$options['readPreference'] = new ReadPreference(ReadPreference::PRIMARY);
		}
		return $options;
	}
}