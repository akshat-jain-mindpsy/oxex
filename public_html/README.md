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
- Enhanced `tabledetail.php` to support section assignments
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
CREATE TABLE csv_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(255) NOT NULL,
    description TEXT,
    created_by VARCHAR(100),
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Purpose**: This table stores CSV export template configurations that can be reused for consistent data exports. It allows administrators to define and save custom export formats for regular reporting needs.

#### CSV Template Columns Table

```sql
CREATE TABLE csv_template_columns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT,
    tbid TINYINT UNSIGNED COMMENT 'Table ID from tabs_tbl',
    stid MEDIUMINT UNSIGNED COMMENT 'Field ID from select_types',
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
