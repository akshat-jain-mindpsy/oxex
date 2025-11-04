<?php
/**
 * Data Transfer Object for a category group (AND group)
 * Can have either:
 * - Simple requirement (requirement_type + required_value when no subfield_rules)
 * - Subfield rules (array of RuleDto when the category has subcategories)
 */
class GroupDto
{
    public $stid;  // top-level category
    public $requirement_type = null;  // only when the group has no sub-fields
    public $required_value = null;
    public $minimum_threshold = null;
    public $max_value = null;  // Maximum count/value allowed (e.g., max 2 presentations)
    
    /** @var RuleDto[] */
    public $rules = [];  // sub-field rules inside the group
}

