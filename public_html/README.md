## Run these commands on your local computer.

#### For an existing project ...

Step 1:  Navigate to your repository's directory: `cd /path/to/your/repo`

Step 2: Configure your local repository to push to the self-hosted repository:

```
git remote add origin ssh://oxex.co.uk@ssh.gb.stackcp.com/home/sites/11a/b/bc221dd6a0/public_html
git push -u origin master
```

<br>

----------

#### To start a new project ...

Step 1:  Clone the self-hosted repository to your local computer and navigate to its directory:
```
git clone ssh://oxex.co.uk@ssh.gb.stackcp.com/home/sites/11a/b/bc221dd6a0/public_html
cd public_html
```

Step 2:  Add additional commands to `stack-deploy.sh` in order to use automatic or manual deployment

Step 3:  Commit the `stack-deploy.sh` file to the project:
```
git add stack-deploy.sh
git commit -m "Change deployment configuration"
git push -u origin master
```

----------

## Recent Updates and Feature Additions

### Graph View Enhancements (June 2023)

The Graph View functionality has been significantly enhanced with the following features:

#### User/Trainee Selection Filtering
- Added the ability to filter graph data by specific trainee/user
- Implemented user-based access controls ensuring admins only see trainees they're authorized to view
- Added visual indicators when data is being filtered for a specific trainee
- Enhanced security by adding permission checks in both frontend and backend

#### Multiple Graph Generation
- Added functionality to generate all possible graphs for a selected table with a single click
- Implemented intelligent field type detection to create meaningful field combinations
- Created categorization of fields into "numeric" and "label" fields for better visualization
- Limited combinations to prevent overwhelming the UI while providing comprehensive data insights

#### Technical Improvements
- Added robust error handling with user-friendly error messages
- Improved AJAX request handling with better response parsing
- Enhanced data filtering at the database query level
- Added trainee-specific data patterns for sample visualizations
- Implemented permission validation at multiple levels

#### UI Enhancements
- Added clear visual indicators for user-filtered data
- Improved card-based layout for multiple graph view
- Enhanced error displays with icons and formatted messages
- Better loading indicators and empty state messages

#### Backend Changes
- Modified get_graph_data.php to support trainee filtering
- Updated get_table_fields.php to support trainee-specific field access
- Added permission checks to enforce data access controls
- Implemented sample data generation that respects trainee context

These enhancements allow administrators to easily visualize data for specific trainees or across the entire system, generating meaningful insights through interactive charts.

### CSV Export Feature (July 2023)

A comprehensive CSV export functionality has been implemented to allow easy data extraction:

#### Export Capabilities
- Added ability to export table data to CSV format
- Implemented filtering options to export specific data subsets
- Added support for exporting trainee-specific data
- Created options for date range filtering on exports

#### Technical Implementation
- Developed a dedicated CSV export handler
- Implemented proper character encoding and CSV formatting
- Added header row with field names for better readability
- Included metadata with export timestamp and filtering criteria

#### User Experience
- Added intuitive export buttons to relevant data tables
- Implemented progress indicators for large exports
- Created success notifications upon completed exports
- Added error handling for failed export attempts

#### Security Features
- Added permission checks to restrict export capabilities
- Implemented data sanitization to prevent formula injection
- Added logging of export activities for audit purposes
- Restricted sensitive field exports based on user permissions

This feature allows administrators and supervisors to extract data for reporting, analysis, and compliance purposes.

### Field Sections and Table Connections (August 2023)

A major system update that connects field sections with tables for better organization:

#### Database Schema Updates
- Created new `section_table_link` table to establish relationships between sections and tables
- Added support for section ordering within tables
- Implemented cascading delete to maintain data integrity
- Updated field sections schema with timestamps for better tracking

```sql
CREATE TABLE section_table_link (
    link_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    tbid TINYINT NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (section_id) REFERENCES field_sections(section_id) ON DELETE CASCADE,
    FOREIGN KEY (tbid) REFERENCES tabs_tbl(tbid) ON DELETE CASCADE,
    UNIQUE KEY section_table_unique (section_id, tbid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE field_sections 
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP;
```

#### Table Management Improvements
- Enhanced `sheetdetail.php` to support section assignments
- Added functionality to edit and delete section connections
- Implemented section reordering capabilities
- Added UI components for managing section relationships

#### Logbook Integration
- Connected sections to specific tables in the logbook
- Implemented filtering to show only relevant sections for each table
- Added dynamic section rendering based on table context
- Improved overall organization of fields within the logbook

#### User Interface Enhancements
- Added section management interface in admin panel
- Created visual indicators for section-table relationships
- Implemented drag-and-drop functionality for section ordering
- Added confirmation dialogs for section deletion

#### Data Integrity Features
- Added proper cleanup when fields or sections are deleted
- Implemented transaction support for multi-table operations
- Added validation to prevent orphaned records
- Improved error handling and user feedback

These changes create a more organized and maintainable system structure by establishing clear relationships between field sections and tables, allowing for better data organization and user experience.

## Database Structure Evolution

This section documents the evolution of the database structure, including all new tables added and schema modifications.

### Pass Standards System (September 2023)

This table introduces a system for defining and tracking completion criteria for trainees.

```sql
CREATE TABLE `pass_standards` (
  `psid` INT NOT NULL AUTO_INCREMENT,
  `standard_name` VARCHAR(255) NOT NULL,
  `tbid` TINYINT UNSIGNED NOT NULL,
  `stid` MEDIUMINT UNSIGNED NULL DEFAULT NULL,
  `requirement_type` ENUM('TOTAL_HOURS', 'UNIQUE_VALUES', 'TOTAL_COUNT', 'UNIQUE_VALUES_IN_RANGE') NOT NULL,
  `required_value` INT NOT NULL,
  `field_value` VARCHAR(255) NULL DEFAULT NULL,
  `parent_standard_id` INT NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `who_by` CHAR(32) NULL DEFAULT NULL,
  `date_added` INT(11) NULL DEFAULT NULL,
  `date_modified` INT(11) NULL DEFAULT NULL,
  PRIMARY KEY (`psid`),
  INDEX `fk_pass_standards_tbid` (`tbid`),
  INDEX `fk_pass_standards_stid` (`stid`),
  INDEX `fk_pass_standards_who_by` (`who_by`),
  INDEX `fk_pass_standards_parent` (`parent_standard_id`),
  CONSTRAINT `fk_pass_standards_tbid` FOREIGN KEY (`tbid`) REFERENCES `tabs_tbl` (`tbid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pass_standards_stid` FOREIGN KEY (`stid`) REFERENCES `select_types` (`stid`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pass_standards_who_by` FOREIGN KEY (`who_by`) REFERENCES `who_there` (`usrkey`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pass_standards_parent` FOREIGN KEY (`parent_standard_id`) REFERENCES `pass_standards` (`psid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
```

**Purpose**: This table introduces a formal system for defining and tracking completion criteria for trainees. It allows administrators to set specific, measurable standards (e.g., "log 20 hours of contact time" or "record at least 5 unique disability types") that can be automatically checked against a trainee's logbook data. This helps in standardizing requirements and providing clear progress feedback.

**Key Features**:
- Supports multiple requirement types: total hours, count of unique values, total entry count, and count of unique values within a numeric range.
- **`parent_standard_id`** allows for the creation of nested rules, where a child standard is only evaluated against the population of data that passes its parent standard. This is critical for complex, multi-step competencies.
- `stid` can be nullable to support standards that are not tied to a specific field, like total hours logged.
- Includes a `field_value` column to allow for more specific requirements, such as counting entries where a particular option was selected.
- Foreign keys with `ON DELETE` rules maintain data integrity.
- An `is_active` flag allows standards to be enabled or disabled without deleting them.

#### How It Works

The system uses these standards to dynamically calculate pass/fail status in real-time from the existing `logbook` and `trainee_log` data, without storing redundant trainee progress data.

**At Runtime:** When checking if a trainee passes a specific category (`tbid`):
1.  The system queries the `pass_standards` table for all active standards linked to that `tbid`.
2.  For each standard found, it dynamically calculates the trainee's current progress from their existing data:
    -   **`TOTAL_HOURS`**: Sums the `session` values from the `logbook` table for the trainee.
    -   **`UNIQUE_VALUES`**: Counts the distinct values in `trainee_log.select_val` for a specific field (`stid`).
    -   **`TOTAL_COUNT`**: Counts the total number of entries in `trainee_log` for a specific field (`stid`).
3.  The calculated value is then compared against the `required_value` for that standard.
4.  A trainee **passes** the category only if they meet **all** the defined standards for it.

**Examples:**
-   **Category "Clinical"**: Might require 100 `TOTAL_HOURS`, 3 `UNIQUE_VALUES` for an 'Age Group' field, and 4 `UNIQUE_VALUES` for an 'Ethnicity' field.
-   **Category "Community"**: Might require 50 `TOTAL_HOURS` and 5 `UNIQUE_VALUES` for a 'Setting' field.
-   **Category "Research"**: Might simply require 20 `TOTAL_COUNT` on a 'Research Activity' field.

This dynamic approach gives administrators the flexibility to:
- Set different and complex requirements for each category.
- Change standards over time without affecting any historical data.
- Have the system automatically apply new or updated standards to all trainees instantly.

### Pass Standard Fields Table (Enhancement for OR Logic)

This new table works with the `pass_standards` table to allow a single rule to check for a condition across multiple fields.

```sql
CREATE TABLE `pass_standard_fields` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `standard_id` INT NOT NULL,
    `stid` MEDIUMINT UNSIGNED NOT NULL,
    FOREIGN KEY (`standard_id`) REFERENCES `pass_standards`(`psid`) ON DELETE CASCADE,
    FOREIGN KEY (`stid`) REFERENCES `select_types`(`stid`) ON DELETE CASCADE,
    UNIQUE KEY `standard_field_unique` (`standard_id`, `stid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Purpose**: This table solves the need for `OR` conditions in rules. For example, a rule might need to check if a trainee has logged "Psychosis" in *either* the "Primary Presenting Clinical Issue" field *or* the "Additional Presenting Issues" field. By linking one standard (`standard_id`) to multiple fields (`stid`), the evaluation engine can check for the condition in any of the specified locations.

### Pass/Fail Evaluation Engine

**File Location:** `public_html/OXEXfolder/pass_standard_functions.php`

**Purpose:** This file contains the centralized "evaluation engine" for dynamically checking a trainee's competency status. It encapsulates all the complex logic for evaluating rules from the `pass_standards` table, including nested parent-child conditions and multi-field OR logic.

**Core Function: `checkCompetencyStatus()`**

This is the primary function to be used throughout the application.

```php
checkCompetencyStatus(string $traineeKey, int $tbid, mysqli $mysqli_connection, int $endDate = null)
```

-   `$traineeKey`: The unique identifier for the trainee being checked.
-   `$tbid`: The ID of the competency (from `tabs_tbl`) to be evaluated.
-   `$mysqli_connection`: The active database connection object.
-   `$endDate`: (Optional) The cohort's end date in `YYYYMMDD` format. If provided and in the past, unmet competencies will be marked as 'Fail'.

**Return Value**

The function returns a detailed associative array, which can be easily converted to JSON for frontend use. This structure provides not just a simple pass/fail/in-progress result, but a complete breakdown of every rule and sub-rule that was checked.

**Example JSON Response:**

```json
{
  "overall_status": "In Progress",
  "breakdown": [
    {
      "standard_name": "AWA: 6+ Unique Clients (18-64)",
      "is_passed": true,
      "current_value": 7,
      "required_value": 6,
      "children": [
        {
          "standard_name": "AWA: Has Psychosis Case",
          "is_passed": false,
          "current_value": 0,
          "required_value": 1,
          "children": []
        }
      ]
    }
  ]
}
```

**How to Use**

Here is a basic example of how to implement this function on a page.

```php
// 1. Include the function file
require_once 'OXEXfolder/pass_standard_functions.php';

// 2. Define the trainee and competency to check
$traineeKey = $_SESSION['trainkey'];
$competencyId = 14; // e.g., 'Adults of Working Age'

// 3. Call the engine to get the status
$status = checkCompetencyStatus($traineeKey, $competencyId, $mysqli);

// 4. Use the results to build your UI
if ($status['overall_status'] === 'Passed') {
    echo "<h2>Competency Passed!</h2>";
} else {
    echo "<h2>Competency In Progress...</h2>";
}

// You can then recursively loop through the 'breakdown' array
// to display a detailed progress report for the trainee.
```

### Subsets / Cohorts System

These tables allow for the grouping of trainees into cohorts or subsets for specific tracking or reporting purposes.

#### Subset Table (`subset_tbl`)

This table defines the cohort itself. The schema should be updated to include an end date.

```sql
ALTER TABLE `subset_tbl`
ADD COLUMN `end_date` INT(8) NULL DEFAULT NULL COMMENT 'YYYYMMDD format graduation/end date for the cohort' AFTER `description`;
```

**Purpose**: Defines a group or cohort. The optional `end_date` marks a graduation or completion date for the entire cohort. When this date passes, any trainee in the cohort who has not met a competency's criteria will be marked as 'Fail' by the evaluation engine.

#### Subset Link Table (`subset_link_tbl`)

**Purpose**: This table links trainees to one or more subsets/cohorts. A trainee's `end_date` for a given competency can be determined by finding the cohort they belong to.

### Section Table Link (August 2023)

This table establishes the relationship between field sections and tables, enabling dynamic organization of fields within tables.

```sql
CREATE TABLE section_table_link (
    link_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    tbid TINYINT NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (section_id) REFERENCES field_sections(section_id) ON DELETE CASCADE,
    FOREIGN KEY (tbid) REFERENCES tabs_tbl(tbid) ON DELETE CASCADE,
    UNIQUE KEY section_table_unique (section_id, tbid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Purpose**: This table was created to solve the problem of organizing fields into logical sections within different tables. Before this, sections were not connected to specific tables, making it difficult to organize fields in a context-sensitive way. The new structure allows sections to be associated with specific tables and ordered appropriately.

**Key Features**:
- CASCADE deletion ensures data integrity when sections or tables are deleted
- Unique constraint prevents duplicate section-table associations
- display_order field allows custom ordering of sections within tables

### CSV Templates System (July 2023)

Two tables were created to support the CSV export functionality:

#### CSV Templates Table

```sql
CREATE TABLE IF NOT EXISTS csv_templates (
    id SERIAL PRIMARY KEY,
    template_name VARCHAR(255) NOT NULL,
    description TEXT,
    created_by VARCHAR(100),
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Purpose**: This table stores CSV export template configurations that can be reused for consistent data exports. It allows administrators to define and save custom export formats for regular reporting needs.

#### CSV Template Columns Table

```sql
CREATE TABLE IF NOT EXISTS csv_template_columns (
    id SERIAL PRIMARY KEY,
    template_id INT,
    tbid SMALLINT, -- Table ID from tabs_tbl (PostgreSQL doesn't have TINYINT UNSIGNED)
    stid INT, -- Field ID from select_types (PostgreSQL doesn't have MEDIUMINT UNSIGNED)
    column_order INT DEFAULT 0,
    FOREIGN KEY (template_id) REFERENCES csv_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (tbid) REFERENCES tabs_tbl(tbid) ON DELETE CASCADE,
    FOREIGN KEY (stid) REFERENCES select_types(stid) ON DELETE CASCADE
);
```

**Purpose**: This table defines which fields (columns) should be included in each CSV export template and in what order they should appear. It connects template definitions to specific tables and fields in the system.

**Key Features**:
- Connects templates to tables and fields
- Maintains column ordering for exports
- CASCADE deletion ensures data integrity when templates are deleted

### Schema Modifications

Several existing tables were modified to support new features:

#### Field Sections Table Update

```sql
ALTER TABLE field_sections 
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP;
```
**Purpose**: Added timestamp fields for better tracking of when sections were created and modified. This improves auditability and helps with troubleshooting.

#### Select Types Table Update

```sql
ALTER TABLE select_types 
ADD COLUMN field_options TEXT NULL AFTER single;
```

**Purpose**: Added support for storing field-specific options as JSON/text data, enabling more customizable field behavior without requiring schema changes for each new option type.

### Design Principles

The database schema evolution follows these key principles:

1. **Referential Integrity**: Foreign key constraints with CASCADE options ensure data remains consistent when records are deleted.

2. **Normalization**: Tables are designed to minimize redundancy while maintaining good performance.

3. **Flexibility**: Generic designs allow for extension without schema changes where possible.

4. **Accountability**: Timestamp and user tracking fields enable audit trails.

5. **Backward Compatibility**: Changes are made in ways that don't break existing functionality.

