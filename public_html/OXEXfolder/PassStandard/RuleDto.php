<?php
/**
 * Data Transfer Object for a sub-field rule
 * Can be a single rule or part of an OR group
 */
class RuleDto
{
    public $subfield_value;
    public $requirement_type;
    public $specific_value;
    public $minimum_threshold = null;
    public $or_group_id = null; // null = base rule, otherwise same id for all OR members
}

