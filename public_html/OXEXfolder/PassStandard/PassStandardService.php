<?php
/**
 * Service layer for Pass Standard business logic
 * Handles validation and conversion between POST data and DTOs
 * Also handles conversion to/from the legacy field_value JSON format
 */
class ValidationException extends Exception {}

class PassStandardService
{
    /**
     * Convert raw POST data into a PassStandardDto
     */
    public function buildDtoFromRequest($payload): PassStandardDto
    {
        $dto = new PassStandardDto();
        
        $dto->psid = isset($payload['psid']) ? (int)$payload['psid'] : 0;
        $dto->standard_name = trim($payload['standard_name'] ?? '');
        $dto->tbid = (int)($payload['tbid'] ?? 0);
        $dto->requirement_type = $payload['requirement_type'] ?? 'TOTAL_COUNT';
        $dto->required_value = (int)($payload['required_value'] ?? 0);
        $dto->is_active = !empty($payload['is_active']);
        // Ensure empty strings are converted to null for nullable integer fields
        $dto->parent_standard_id = (!empty($payload['parent_standard_id']) && $payload['parent_standard_id'] !== '') 
            ? (int)$payload['parent_standard_id'] : null;
        $dto->minimum_threshold = isset($payload['minimum_threshold']) && $payload['minimum_threshold'] !== '' 
            ? (float)$payload['minimum_threshold'] : null;
        
        // ---------- rules (OR groups) ----------
        $dto->rules = [];
        if (!empty($payload['subfield_rules']) && is_array($payload['subfield_rules'])) {
            $orGroupCounter = 1;
            foreach ($payload['subfield_rules'] as $idx => $rawRule) {
                // Support OR groups via any_of[]
                if (!empty($rawRule['any_of']) && is_array($rawRule['any_of'])) {
                    $orGroupId = $orGroupCounter++;
                    foreach ($rawRule['any_of'] as $alt) {
                        if (!empty($alt['subfield_value']) && !empty($alt['requirement_type']) && !empty($alt['specific_value'])) {
                            $rule = new RuleDto();
                            $rule->subfield_value = (int)$alt['subfield_value'];
                            $rule->requirement_type = $alt['requirement_type'];
                            $rule->specific_value = $alt['specific_value'];
                            $rule->minimum_threshold = isset($alt['minimum_threshold']) && $alt['minimum_threshold'] !== '' 
                                ? (float)$alt['minimum_threshold'] : null;
                            $rule->or_group_id = $orGroupId;
                            $dto->rules[] = $rule;
                        }
                    }
                } elseif (!empty($rawRule['subfield_values']) || !empty($rawRule['subfield_value'])) {
                    // Single rule (no OR group)
                    $subfieldVal = !empty($rawRule['subfield_values']) ? $rawRule['subfield_values'] : $rawRule['subfield_value'];
                    if (!empty($subfieldVal) && !empty($rawRule['requirement_type']) && !empty($rawRule['specific_value'])) {
                        $rule = new RuleDto();
                        $rule->subfield_value = (int)$subfieldVal;
                        $rule->requirement_type = $rawRule['requirement_type'];
                        $rule->specific_value = $rawRule['specific_value'];
                        $rule->minimum_threshold = isset($rawRule['minimum_threshold']) && $rawRule['minimum_threshold'] !== '' 
                            ? (float)$rawRule['minimum_threshold'] : null;
                        $rule->or_group_id = null;  // single rule, no OR group
                        $dto->rules[] = $rule;
                    }
                }
            }
        }
        
        // ---------- hour sources ----------
        $dto->hourSources = [];
        if (!empty($payload['hour_sources']) && is_array($payload['hour_sources'])) {
            foreach ($payload['hour_sources'] as $rawSrc) {
                if (!empty($rawSrc['category_stid'])) {
                    $src = new SourceDto();
                    $src->category_stid = (int)$rawSrc['category_stid'];
                    $src->category_value = !empty($rawSrc['category_value']) ? $rawSrc['category_value'] : null;
                    $src->max_value = isset($rawSrc['max_value']) && $rawSrc['max_value'] !== '' 
                        ? (float)$rawSrc['max_value'] : null;
                    $dto->hourSources[] = $src;
                }
            }
        }
        
        // ---------- category groups ----------
        $dto->categoryGroups = [];
        if (!empty($payload['category_groups']) && is_array($payload['category_groups'])) {
            foreach ($payload['category_groups'] as $rawGrp) {
                if (!empty($rawGrp['stid'])) {
                    $grp = new GroupDto();
                    $grp->stid = (int)$rawGrp['stid'];
                    $grp->requirement_type = !empty($rawGrp['requirement_type']) ? $rawGrp['requirement_type'] : null;
                    $grp->required_value = isset($rawGrp['required_value']) && $rawGrp['required_value'] !== '' 
                        ? (int)$rawGrp['required_value'] : null;
                    $grp->minimum_threshold = isset($rawGrp['minimum_threshold']) && $rawGrp['minimum_threshold'] !== '' 
                        ? (float)$rawGrp['minimum_threshold'] : null;
                    $grp->max_value = isset($rawGrp['max_value']) && $rawGrp['max_value'] !== '' 
                        ? (int)$rawGrp['max_value'] : null;
                    
                    // sub-field rules inside the group
                    $grp->rules = [];
                    if (!empty($rawGrp['subfield_rules']) && is_array($rawGrp['subfield_rules'])) {
                        $orGroupCounter = 1;
                        foreach ($rawGrp['subfield_rules'] as $rawRule) {
                            if (!empty($rawRule['subfield_values']) || !empty($rawRule['subfield_value'])) {
                                $subfieldVal = !empty($rawRule['subfield_values']) ? $rawRule['subfield_values'] : $rawRule['subfield_value'];
                                if (!empty($subfieldVal) && !empty($rawRule['requirement_type']) && !empty($rawRule['specific_value'])) {
                                    $rule = new RuleDto();
                                    $rule->subfield_value = (int)$subfieldVal;
                                    $rule->requirement_type = $rawRule['requirement_type'];
                                    $rule->specific_value = $rawRule['specific_value'];
                                    $rule->minimum_threshold = isset($rawRule['minimum_threshold']) && $rawRule['minimum_threshold'] !== '' 
                                        ? (float)$rawRule['minimum_threshold'] : null;
                                    $rule->or_group_id = null;  // TODO: support OR groups in category groups if needed
                                    $grp->rules[] = $rule;
                                }
                            }
                        }
                    }
                    $dto->categoryGroups[] = $grp;
                }
            }
        }
        
        return $dto;
    }
    
    /**
     * Validate the DTO according to business rules
     * @throws ValidationException
     */
    public function validate(PassStandardDto $dto): void
    {
        if (empty($dto->standard_name)) {
            throw new ValidationException('Standard name is required');
        }
        if ($dto->tbid <= 0) {
            throw new ValidationException('You must select a sheet (table)');
        }
        if ($dto->required_value < 0) {
            throw new ValidationException('Required value must be 0 or greater');
        }
        
        // Requirement-type specific checks
        if ($dto->requirement_type === 'TOTAL_HOURS_COMBINED' && count($dto->hourSources) === 0) {
            throw new ValidationException('At least one hour source must be defined for a combined-hours rule');
        }
        
        if ($dto->requirement_type === 'PER_CASE_MINIMUM') {
            if ($dto->minimum_threshold === null || $dto->minimum_threshold <= 0) {
                throw new ValidationException('PER_CASE_MINIMUM rules require a positive minimum threshold value');
            }
        }
        
        // Validate each rule
        foreach ($dto->rules as $r) {
            if ($r->subfield_value <= 0) {
                throw new ValidationException('Every rule must reference a sub-field');
            }
            if (empty($r->requirement_type) || $r->specific_value === '') {
                throw new ValidationException('Every rule needs a requirement type and a value');
            }
            if ($r->requirement_type === 'PER_CASE_MINIMUM' && ($r->minimum_threshold === null || $r->minimum_threshold <= 0)) {
                throw new ValidationException('PER_CASE_MINIMUM rules need a positive minimum_threshold');
            }
        }
        
        // Category-group validation
        foreach ($dto->categoryGroups as $g) {
            if ($g->stid <= 0) {
                throw new ValidationException('Every category group must have a top-level category (stid)');
            }
            
            // If the group has sub-field rules we ignore the simple requirement fields.
            if (empty($g->rules) && $g->requirement_type) {
                // simple requirement – we need a value
                if ($g->required_value === null) {
                    throw new ValidationException('Simple requirement groups need a required value');
                }
                if ($g->requirement_type === 'PER_CASE_MINIMUM' && ($g->minimum_threshold === null || $g->minimum_threshold <= 0)) {
                    throw new ValidationException('PER_CASE_MINIMUM groups need a minimum threshold');
                }
            }
            
            // validate each sub-rule
            foreach ($g->rules as $gr) {
                if ($gr->subfield_value <= 0) {
                    throw new ValidationException('Group sub-rule must reference a sub-field');
                }
                if (empty($gr->requirement_type) || $gr->specific_value === '') {
                    throw new ValidationException('Group sub-rule must have type + value');
                }
                if ($gr->requirement_type === 'PER_CASE_MINIMUM' && ($gr->minimum_threshold === null || $gr->minimum_threshold <= 0)) {
                    throw new ValidationException('PER_CASE_MINIMUM group sub-rule needs a minimum threshold');
                }
            }
        }
    }
    
    /**
     * Convert DTO to legacy field_value JSON string
     * This maintains backward compatibility with existing code
     */
    public function dtoToFieldValue(PassStandardDto $dto): ?string
    {
        // Priority: category_groups > hour_sources > subfield_rules > simple field_value
        
        // 1. Category groups (AND condition)
        if (!empty($dto->categoryGroups)) {
            $categoryGroupsData = ['category_groups' => []];
            foreach ($dto->categoryGroups as $group) {
                $grpData = [
                    'stid' => $group->stid
                ];
                
                // If group has subfield rules, include them
                if (!empty($group->rules)) {
                    $grpData['subfield_rules'] = [];
                    foreach ($group->rules as $rule) {
                        $ruleData = [
                            'subfield_value' => $rule->subfield_value,
                            'requirement_type' => $rule->requirement_type,
                            'specific_value' => $rule->specific_value
                        ];
                        if ($rule->minimum_threshold !== null) {
                            $ruleData['minimum_threshold'] = $rule->minimum_threshold;
                        }
                        $grpData['subfield_rules'][] = $ruleData;
                    }
                } else {
                    // Simple requirement (no subfields)
                    if ($group->requirement_type) {
                        $grpData['requirement_type'] = $group->requirement_type;
                        $grpData['required_value'] = $group->required_value;
                        if ($group->minimum_threshold !== null) {
                            $grpData['minimum_threshold'] = $group->minimum_threshold;
                        }
                        if ($group->max_value !== null) {
                            $grpData['max_value'] = $group->max_value;
                        }
                    }
                }
                
                $categoryGroupsData['category_groups'][] = $grpData;
            }
            return json_encode($categoryGroupsData);
        }
        
        // 2. Combined hours sources
        if ($dto->requirement_type === 'TOTAL_HOURS_COMBINED' && !empty($dto->hourSources)) {
            $totalOf = [];
            foreach ($dto->hourSources as $src) {
                $srcData = ['category_stid' => $src->category_stid];
                if ($src->category_value !== null) {
                    $srcData['category_value'] = $src->category_value;
                }
                if ($src->max_value !== null) {
                    $srcData['max_value'] = $src->max_value;
                }
                $totalOf[] = $srcData;
            }
            return json_encode(['total_of' => $totalOf]);
        }
        
        // 3. Subfield rules (OR groups)
        if (!empty($dto->rules)) {
            $processedRules = [];
            $currentOrGroup = [];
            $currentOrGroupId = null;
            
            foreach ($dto->rules as $rule) {
                $ruleData = [
                    'subfield_value' => $rule->subfield_value,
                    'requirement_type' => $rule->requirement_type,
                    'specific_value' => $rule->specific_value
                ];
                if ($rule->minimum_threshold !== null) {
                    $ruleData['minimum_threshold'] = $rule->minimum_threshold;
                }
                
                // Group rules by or_group_id
                if ($rule->or_group_id !== null) {
                    if ($currentOrGroupId !== $rule->or_group_id) {
                        // Save previous group if exists
                        if (!empty($currentOrGroup)) {
                            $processedRules[] = ['any_of' => $currentOrGroup];
                            $currentOrGroup = [];
                        }
                        $currentOrGroupId = $rule->or_group_id;
                    }
                    $currentOrGroup[] = $ruleData;
                } else {
                    // Save previous OR group if exists
                    if (!empty($currentOrGroup)) {
                        $processedRules[] = ['any_of' => $currentOrGroup];
                        $currentOrGroup = [];
                        $currentOrGroupId = null;
                    }
                    // Single rule (no OR group)
                    $processedRules[] = $ruleData;
                }
            }
            
            // Save final OR group if exists
            if (!empty($currentOrGroup)) {
                $processedRules[] = ['any_of' => $currentOrGroup];
            }
            
            if (!empty($processedRules)) {
                return 'SUBFIELD_RULES:' . json_encode($processedRules);
            }
        }
        
        return null;
    }
    
    /**
     * Build a DTO from a database row
     * Used when loading an existing standard for editing
     */
    public function buildDtoFromDbRow(array $row): PassStandardDto
    {
        $dto = new PassStandardDto();
        $dto->psid = (int)$row['psid'];
        $dto->standard_name = $row['standard_name'];
        $dto->tbid = (int)$row['tbid'];
        $dto->requirement_type = $row['requirement_type'];
        $dto->required_value = (int)$row['required_value'];
        $dto->is_active = (bool)$row['is_active'];
        $dto->parent_standard_id = $row['parent_standard_id'] ? (int)$row['parent_standard_id'] : null;
        $dto->minimum_threshold = isset($row['minimum_threshold']) && $row['minimum_threshold'] !== null 
            ? (float)$row['minimum_threshold'] : null;
        
        // Convert field_value JSON to DTO properties
        $this->fieldValueToDto($row['field_value'] ?? null, $dto);
        
        return $dto;
    }
    
    /**
     * Convert legacy field_value JSON string to DTO
     * Used when loading an existing standard
     */
    public function fieldValueToDto(?string $fieldValue, PassStandardDto $dto): void
    {
        if (empty($fieldValue)) {
            return;
        }
        
        // Category groups
        if (strpos($fieldValue, '{"category_groups"') === 0) {
            $data = json_decode($fieldValue, true);
            if (isset($data['category_groups']) && is_array($data['category_groups'])) {
                foreach ($data['category_groups'] as $rawGrp) {
                    $grp = new GroupDto();
                    $grp->stid = (int)$rawGrp['stid'];
                    $grp->requirement_type = $rawGrp['requirement_type'] ?? null;
                    $grp->required_value = isset($rawGrp['required_value']) ? (int)$rawGrp['required_value'] : null;
                    $grp->minimum_threshold = isset($rawGrp['minimum_threshold']) ? (float)$rawGrp['minimum_threshold'] : null;
                    $grp->max_value = isset($rawGrp['max_value']) ? (int)$rawGrp['max_value'] : null;
                    
                    if (!empty($rawGrp['subfield_rules']) && is_array($rawGrp['subfield_rules'])) {
                        foreach ($rawGrp['subfield_rules'] as $rawRule) {
                            $rule = new RuleDto();
                            $rule->subfield_value = (int)$rawRule['subfield_value'];
                            $rule->requirement_type = $rawRule['requirement_type'];
                            $rule->specific_value = $rawRule['specific_value'];
                            $rule->minimum_threshold = isset($rawRule['minimum_threshold']) ? (float)$rawRule['minimum_threshold'] : null;
                            // Detect OR groups
                            if (isset($rawRule['any_of']) && is_array($rawRule['any_of'])) {
                                $orGroupId = 1;
                                foreach ($rawRule['any_of'] as $alt) {
                                    $altRule = new RuleDto();
                                    $altRule->subfield_value = (int)$alt['subfield_value'];
                                    $altRule->requirement_type = $alt['requirement_type'];
                                    $altRule->specific_value = $alt['specific_value'];
                                    $altRule->minimum_threshold = isset($alt['minimum_threshold']) ? (float)$alt['minimum_threshold'] : null;
                                    $altRule->or_group_id = $orGroupId;
                                    $grp->rules[] = $altRule;
                                }
                            } else {
                                $grp->rules[] = $rule;
                            }
                        }
                    }
                    $dto->categoryGroups[] = $grp;
                }
            }
            return;
        }
        
        // Combined hours (total_of)
        if (strpos($fieldValue, '{"total_of"') === 0 || strpos($fieldValue, '{"hour_sources"') === 0) {
            $data = json_decode($fieldValue, true);
            $sources = $data['total_of'] ?? $data['hour_sources'] ?? [];
            foreach ($sources as $rawSrc) {
                $src = new SourceDto();
                $src->category_stid = (int)$rawSrc['category_stid'];
                $src->category_value = $rawSrc['category_value'] ?? null;
                $src->max_value = isset($rawSrc['max_value']) ? (float)$rawSrc['max_value'] : null;
                $dto->hourSources[] = $src;
            }
            return;
        }
        
        // Subfield rules
        if (strpos($fieldValue, 'SUBFIELD_RULES:') === 0) {
            $json = substr($fieldValue, strlen('SUBFIELD_RULES:'));
            $rules = json_decode($json, true);
            if (is_array($rules)) {
                $orGroupId = 1;
                foreach ($rules as $rawRule) {
                    if (isset($rawRule['any_of']) && is_array($rawRule['any_of'])) {
                        // OR group
                        foreach ($rawRule['any_of'] as $alt) {
                            $rule = new RuleDto();
                            $rule->subfield_value = (int)$alt['subfield_value'];
                            $rule->requirement_type = $alt['requirement_type'];
                            $rule->specific_value = $alt['specific_value'];
                            $rule->minimum_threshold = isset($alt['minimum_threshold']) ? (float)$alt['minimum_threshold'] : null;
                            $rule->or_group_id = $orGroupId;
                            $dto->rules[] = $rule;
                        }
                        $orGroupId++;
                    } else {
                        // Single rule
                        $rule = new RuleDto();
                        $rule->subfield_value = (int)$rawRule['subfield_value'];
                        $rule->requirement_type = $rawRule['requirement_type'];
                        $rule->specific_value = $rawRule['specific_value'];
                        $rule->minimum_threshold = isset($rawRule['minimum_threshold']) ? (float)$rawRule['minimum_threshold'] : null;
                        $rule->or_group_id = null;
                        $dto->rules[] = $rule;
                    }
                }
            }
            return;
        }
        
        // Simple field_value (just a string, e.g., "18-64")
        // This is stored directly, no conversion needed
    }
}

