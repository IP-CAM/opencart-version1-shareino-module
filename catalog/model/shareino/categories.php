<?php

class ModelShareinoCategories extends Model
{

    public function getCategories()
    {
        $query = $this->db->query("SELECT "
            . DB_PREFIX . "category.category_id As id, "
            . DB_PREFIX . "category.parent_id, "
            . DB_PREFIX . "category_description.name "
            . "FROM " . DB_PREFIX . "category INNER JOIN " . DB_PREFIX . "category_description "
            . "ON " . DB_PREFIX . "category.category_id = " . DB_PREFIX . "category_description.category_id AND `language_id` = " . (int)$this->config->get('config_language_id'));

        if ($query->num_rows) {
            return json_encode($query->rows);
        }
        return false;
    }

}
