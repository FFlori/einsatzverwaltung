<?php
namespace abrain\Einsatzverwaltung;

use abrain\Einsatzverwaltung\CustomFields\ColorPicker;
use abrain\Einsatzverwaltung\CustomFields\CustomField;
use abrain\Einsatzverwaltung\CustomFields\NumberInput;
use abrain\Einsatzverwaltung\CustomFields\PostSelector;
use abrain\Einsatzverwaltung\CustomFields\TextInput;
use abrain\Einsatzverwaltung\Types\CustomTaxonomy;
use abrain\Einsatzverwaltung\Types\CustomType;
use function add_action;

/**
 * Class TaxonomyCustomFields
 * @package abrain\Einsatzverwaltung
 */
class TaxonomyCustomFields
{
    /**
     * @var array
     */
    private $taxonomyFields;

    /**
     * TaxonomyCustomFields constructor.
     */
    public function __construct()
    {
        $this->taxonomyFields = array();

        add_action('edited_term', array($this, 'saveTerm'), 10, 3);
        add_action('created_term', array($this, 'saveTerm'), 10, 3);
    }

    /**
     * @param CustomType $customType
     * @param TextInput $textInput
     */
    public function addTextInput(CustomType $customType, TextInput $textInput)
    {
        $this->add($customType, $textInput);
    }

    /**
     * @param CustomType $customType
     * @param ColorPicker $colorPicker
     */
    public function addColorpicker(CustomType $customType, ColorPicker $colorPicker)
    {
        $this->add($customType, $colorPicker);
    }

    /**
     * @param CustomType $customType
     * @param NumberInput $numberInput
     */
    public function addNumberInput(CustomType $customType, NumberInput $numberInput)
    {
        $this->add($customType, $numberInput);
    }

    /**
     * @param CustomType $customType
     * @param PostSelector $postSelector
     */
    public function addPostSelector(CustomType $customType, PostSelector $postSelector)
    {
        $this->add($customType, $postSelector);
    }

    /**
     * @param CustomType $customType
     * @param CustomField $customField
     */
    private function add(CustomType $customType, CustomField $customField)
    {
        if ($customType instanceof CustomTaxonomy) {
            $taxonomy = $customType->getSlug();

            if (!array_key_exists($taxonomy, $this->taxonomyFields)) {
                $this->taxonomyFields[$taxonomy] = array();
                add_action("{$taxonomy}_add_form_fields", array($this, 'onAddFormFields'));
                add_action("{$taxonomy}_edit_form_fields", array($this, 'onEditFormFields'), 10, 2);
                add_action("manage_edit-{$taxonomy}_columns", array($this, 'onCustomColumns'));
                add_action("manage_{$taxonomy}_custom_column", array($this, 'onColumnContent'), 10, 3);
            }

            $this->taxonomyFields[$taxonomy][$customField->key] = $customField;
        }
    }

    /**
     * @param string $taxonomy The taxonomy slug.
     */
    public function onAddFormFields($taxonomy)
    {
        if (!array_key_exists($taxonomy, $this->taxonomyFields) || !is_array($this->taxonomyFields[$taxonomy])) {
            return;
        }

        /** @var CustomField $field */
        foreach ($this->taxonomyFields[$taxonomy] as $field) {
            echo $field->getAddTermMarkup();
        }
    }

    /**
     * @param object $tag Current taxonomy term object.
     * @param string $taxonomy Current taxonomy slug.
     */
    public function onEditFormFields($tag, $taxonomy)
    {
        if (!array_key_exists($taxonomy, $this->taxonomyFields) || !is_array($this->taxonomyFields[$taxonomy])) {
            return;
        }

        /** @var CustomField $field */
        foreach ($this->taxonomyFields[$taxonomy] as $field) {
            echo $field->getEditTermMarkup($tag);
        }
    }

    /**
     * Fügt für die zusätzlichen Felder zusätzliche Spalten in der Übersicht ein
     *
     * @param array $columns
     * @return array
     */
    public function onCustomColumns($columns)
    {
        $screen = get_current_screen();

        if (empty($screen)) {
            return $columns;
        }

        $taxonomy = $screen->taxonomy;
        if (!array_key_exists($taxonomy, $this->taxonomyFields) || !is_array($this->taxonomyFields[$taxonomy])) {
            return $columns;
        }

        $filteredColumns = array();

        foreach ($columns as $slug => $name) {
            $filteredColumns[$slug] = $name;
            if ($slug == 'description') {
                /** @var CustomField $field */
                foreach ($this->taxonomyFields[$taxonomy] as $field) {
                    $filteredColumns[$field->key] = $field->label;
                }
            }
        }

        return $filteredColumns;
    }

    /**
     * Filterfunktion für den Inhalt der selbst angelegten Spalten
     *
     * @param string $string Leerer String.
     * @param string $columnName Name der Spalte
     * @param int $termId Term ID
     *
     * @return string Inhalt der Spalte
     */
    public function onColumnContent($string, $columnName, $termId)
    {
        $term = get_term($termId);
        if (empty($term) || is_wp_error($term)) {
            return '';
        }

        $taxonomy = $term->taxonomy;
        if (!array_key_exists($taxonomy, $this->taxonomyFields) || !is_array($this->taxonomyFields[$taxonomy])) {
            return '';
        }

        $fields = $this->taxonomyFields[$taxonomy];
        if (!array_key_exists($columnName, $fields)) {
            return '';
        }

        /** @var CustomField $customField */
        $customField = $fields[$columnName];
        return $customField->getColumnContent($termId);
    }

    /**
     * Speichert zusätzliche Infos zu Terms als options ab
     *
     * @param int $termId Term ID
     * @param int $ttId Term taxonomy ID
     * @param string $taxonomy Taxonomy slug
     */
    public function saveTerm($termId, $ttId, $taxonomy)
    {
        if (!array_key_exists($taxonomy, $this->taxonomyFields) || !is_array($this->taxonomyFields[$taxonomy])) {
            return;
        }

        /** @var CustomField $field */
        foreach ($this->taxonomyFields[$taxonomy] as $field) {
            $value = filter_input(INPUT_POST, $field->key, FILTER_SANITIZE_STRING);

            update_term_meta($termId, $field->key, empty($value) ? $field->defaultValue : $value);
        }
    }
}
