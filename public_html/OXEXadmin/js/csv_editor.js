document.addEventListener('DOMContentLoaded', function() {
    const columnsContainer = document.getElementById('columns-container');
    const addColumnBtn = document.getElementById('add-column-btn');
    const csvTemplateForm = document.getElementById('csv-template-form');

    // Function to fetch table fields dynamically
    function fetchTableFields(tableSelect, fieldSelect) {
        const tableId = tableSelect.value;
        const loadingOption = document.createElement('option');
        loadingOption.text = 'Loading fields...';
        loadingOption.disabled = true;
        loadingOption.selected = true;

        // Clear existing options and add loading indicator
        fieldSelect.innerHTML = '';
        fieldSelect.appendChild(loadingOption);

        // Fetch fields for the selected table
        fetch(`ajax/get_table_fields.php?table_id=${tableId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(fields => {
                // Clear previous options
                fieldSelect.innerHTML = '';

                // Add default option
                const defaultOption = document.createElement('option');
                defaultOption.text = 'Select a Field';
                defaultOption.value = '';
                fieldSelect.appendChild(defaultOption);

                // Populate fields
                fields.forEach(field => {
                    const option = document.createElement('option');
                    option.value = field.stid;
                    option.text = field.str;
                    fieldSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error fetching fields:', error);
                fieldSelect.innerHTML = '';
                const errorOption = document.createElement('option');
                errorOption.text = 'Error loading fields';
                errorOption.disabled = true;
                fieldSelect.appendChild(errorOption);
            });
    }

    // Function to create a new column configuration row
    function createColumnConfigRow(tables = []) {
        const columnConfig = document.createElement('div');
        columnConfig.className = 'column-config';

        // Table select
        const tableSelect = document.createElement('select');
        tableSelect.className = 'form-control table-select';
        tableSelect.name = 'table[]';

        // Default option
        const defaultTableOption = document.createElement('option');
        defaultTableOption.text = 'Select a Table';
        defaultTableOption.value = '';
        tableSelect.appendChild(defaultTableOption);

        // Populate tables
        tables.forEach(table => {
            const option = document.createElement('option');
            option.value = table.tbid;
            option.text = table.name;
            tableSelect.appendChild(option);
        });

        // Field select
        const fieldSelect = document.createElement('select');
        fieldSelect.className = 'form-control field-select';
        fieldSelect.name = 'field[]';

        const defaultFieldOption = document.createElement('option');
        defaultFieldOption.text = 'Select a Field';
        defaultFieldOption.value = '';
        fieldSelect.appendChild(defaultFieldOption);

        // Remove column button
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-danger remove-column';
        removeBtn.textContent = 'Remove';

        // Event listener for table selection to fetch fields
        tableSelect.addEventListener('change', () => {
            fetchTableFields(tableSelect, fieldSelect);
        });

        // Event listener for remove button
        removeBtn.addEventListener('click', () => {
            columnConfig.remove();
        });

        // Append elements
        columnConfig.appendChild(tableSelect);
        columnConfig.appendChild(fieldSelect);
        columnConfig.appendChild(removeBtn);

        return columnConfig;
    }

    // Add column button event listener
    addColumnBtn.addEventListener('click', () => {
        // Fetch tables via AJAX to populate the new row
        fetch('ajax/get_tables.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(tables => {
                const newColumnRow = createColumnConfigRow(tables);
                columnsContainer.appendChild(newColumnRow);
            })
            .catch(error => {
                console.error('Error fetching tables:', error);
                alert('Failed to load tables. Please try again.');
            });
    });

    // Form submission handler
    csvTemplateForm.addEventListener('submit', function(event) {
        event.preventDefault(); // Prevent default form submission

        const tableSelects = document.querySelectorAll('.table-select');
        const fieldSelects = document.querySelectorAll('.field-select');

        // Validate columns
        let isValid = true;
        const columns = [];
        tableSelects.forEach((tableSelect, index) => {
            const fieldSelect = fieldSelects[index];
            
            if (!tableSelect.value || !fieldSelect.value) {
                isValid = false;
                alert('Please select both a table and a field for each column.');
                return;
            }

            columns.push({
                table_id: parseInt(tableSelect.value),
                field_id: parseInt(fieldSelect.value)
            });
        });

        if (!isValid) return;

        // Prepare template data
        const templateData = {
            template_id: document.querySelector('input[name="template_id"]')?.value || null,
            template_name: document.getElementById('template-name').value,
            description: document.getElementById('description').value,
            columns: columns,
            delimiter: document.getElementById('delimiter').value,
            enclosure: document.getElementById('enclosure').value,
            header_row: parseInt(document.getElementById('header-row').value),
            max_rows: parseInt(document.getElementById('max-rows').value),
            export_type: document.getElementById('export-type').value
        };

        // Send AJAX request to save template
        fetch('ajax/save_csv_template.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(templateData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Show success message
                alert('Template saved successfully!');
                
                // Redirect or update page as needed
                window.location.href = `csv_editor.php?template_id=${data.template_id}`;
            } else {
                // Show error message
                alert(`Error: ${data.message}`);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while saving the template.');
        });
    });

    // Optional: Delete template button handler
    const deleteTemplateBtn = document.getElementById('delete-template-btn');
    if (deleteTemplateBtn) {
        deleteTemplateBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete this template?')) {
                // You might want to add an AJAX call to delete the template
                // For now, this is a placeholder
                window.location.href = 'delete_template.php?id=' + this.dataset.templateId;
            }
        });
    }
}); 