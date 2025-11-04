<?php
/**
 * Data Transfer Object for a combined-hours source
 * Only used when requirement_type = TOTAL_HOURS_COMBINED
 */
class SourceDto
{
    public $category_stid;
    public $category_value = null;  // null = "any"
    public $max_value = null;  // Maximum cap for this source (e.g., 40 hours max)
}

