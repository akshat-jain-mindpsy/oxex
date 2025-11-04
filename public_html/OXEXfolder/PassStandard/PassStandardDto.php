<?php
/**
 * Data Transfer Object for a complete Pass Standard
 * Contains all the data needed to represent a standard and its rules
 */
class PassStandardDto
{
    public $psid = 0;
    public $standard_name = '';
    public $tbid = 0;
    public $requirement_type = 'TOTAL_COUNT';
    public $required_value = 0;
    public $is_active = true;
    public $parent_standard_id = null;
    public $minimum_threshold = null;  // for PER_CASE_MINIMUM requirement type
    
    /** @var RuleDto[] */
    public $rules = [];  // sub-field rules (OR groups)
    
    /** @var SourceDto[] */
    public $hourSources = [];  // only when requirement_type = TOTAL_HOURS_COMBINED
    
    /** @var GroupDto[] */
    public $categoryGroups = [];  // AND groups
}

