<?php
namespace abrain\Einsatzverwaltung;

use abrain\Einsatzverwaltung\CustomFields\ColorPicker;
use abrain\Einsatzverwaltung\CustomFields\CustomField;
use abrain\Einsatzverwaltung\CustomFields\NumberInput;
use abrain\Einsatzverwaltung\CustomFields\PostSelector;
use abrain\Einsatzverwaltung\CustomFields\TextInput;
use abrain\Einsatzverwaltung\Types\CustomPostType;
use abrain\Einsatzverwaltung\Types\CustomTaxonomy;
use abrain\Einsatzverwaltung\Types\CustomType;
use abrain\Einsatzverwaltung\Types\Report;
use function add_action;

/**
 * Keeps track of the custom fields of our custom types
 *
 * @package abrain\Einsatzverwaltung
 */
class CustomFieldsRepository
{
    /**
     * @var array
     */
    private $postTypeFields;

    /**
     * @var array
     */
    private $taxonomyFields;

    /**
     * TaxonomyCustomFields constructor.
     */
    public function __construct()
    {
        $this->postTypeFields = array();
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
            $taxonomy = $customType::getSlug();

            if (!array_key_exists($taxonomy, $this->taxonomyFields)) {
                $this->taxonomyFields[$taxonomy] = array();
                add_action("{$taxonomy}_add_form_fields", array($this, 'onAddFormFields'));
                add_action("{$taxonomy}_edit_form_fields", array($this, 'onEditFormFields'), 10, 2);
                add_action("manage_edit-{$taxonomy}_columns", array($this, 'onCustomColumns'));
                add_action("manage_{$taxonomy}_custom_column", array($this, 'onTaxonomyColumnContent'), 10, 3);
            }

            $this->taxonomyFields[$taxonomy][$customField->key] = $customField;
        } elseif ($customType instanceof CustomPostType) {
            $postType = $customType::getSlug();

            if (!array_key_exists($postType, $this->postTypeFields)) {
                $this->postTypeFields[$postType] = array();
                add_filter("manage_edit-{$postType}_columns", array($this, 'onCustomColumns'));
                add_action("manage_{$postType}_posts_custom_column", array($this, 'onPostColumnContent'), 10, 2);
            }

            $this->postTypeFields[$postType][$customField->key] = $customField;
        }
    }

    /**
     * Checks if we can iterate over custom fields for a certain post type.
     *
     * @param string $postType
     *
     * @return bool
     */
    private function hasPostType($postType)
    {
        return array_key_exists($postType, $this->postTypeFields) && is_array($this->postTypeFields[$postType]);
    }

    /**
     * Checks if we can iterate over custom fields for a certain taxonomy.
     *
     * @param string $taxonomy
     *
     * @return bool
     */
    private function hasTaxonomy($taxonomy)
    {
        return array_key_exists($taxonomy, $this->taxonomyFields) && is_array($this->taxonomyFields[$taxonomy]);
    }

    /**
     * @param string $taxonomy The taxonomy slug.
     */
    public function onAddFormFields($taxonomy)
    {
        if (!$this->hasTaxonomy($taxonomy)) {
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
        if (!$this->hasTaxonomy($taxonomy)) {
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

        if ($screen->post_type === Report::getSlug() && !empty($screen->taxonomy)) {
            $taxonomy = $screen->taxonomy;
            if (!$this->hasTaxonomy($taxonomy)) {
                return $columns;
            }

            // Add the columns after the description column
            $index = array_search('description', array_keys($columns));
            $index = is_numeric($index) ? $index + 1 : count($columns);
            $before = array_slice($columns, 0, $index, true);
            $after = array_slice($columns, $index, null, true);

            $columnsToAdd = array();
            /** @var CustomField $field */
            foreach ($this->taxonomyFields[$taxonomy] as $field) {
                $columnsToAdd[$field->key] = $field->label;
            }

            return array_merge($before, $columnsToAdd, $after);
        }

        $postType = $screen->post_type;
        if (!$this->hasPostType($postType)) {
            return $columns;
        }

        /** @var CustomField $field */
        foreach ($this->postTypeFields[$postType] as $field) {
            $columns[$field->key] = $field->label;
        }

        return $columns;
    }

    /**
     * @param string $column
     * @param int $postId
     */
    public function onPostColumnContent($column, $postId)
    {
        echo 'content'; // TODO
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
    public function onTaxonomyColumnContent($string, $columnName, $termId)
    {
        $term = get_term($termId);
        if (empty($term) || is_wp_error($term)) {
            return '';
        }

        $taxonomy = $term->taxonomy;
        if (!$this->hasTaxonomy($taxonomy)) {
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
        if (!$this->hasTaxonomy($taxonomy)) {
            return;
        }

        /** @var CustomField $field */
        foreach ($this->taxonomyFields[$taxonomy] as $field) {
            $value = filter_input(INPUT_POST, $field->key, FILTER_SANITIZE_STRING);

            update_term_meta($termId, $field->key, empty($value) ? $field->defaultValue : $value);
        }
    }
}
