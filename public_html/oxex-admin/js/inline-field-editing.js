/**
 * Inline Field Editing Functionality for tabledetail.php
 */

// Wait for document and jQuery to be ready
document.addEventListener('DOMContentLoaded', function() {
    // Check if jQuery is available
    if (typeof jQuery === 'undefined') {
        console.error('jQuery is not loaded! inline-field-editing.js requires jQuery.');
        return;
    }
    
    // Now use jQuery
    $(document).ready(function() {
        // Debug: Check field card elements on page load
        console.log("Document ready - checking field cards");
        $('.field-card').each(function() {
            const fieldId = $(this).data('field-id');
            const fieldName = $(this).find('.field-name-text').text().trim();
            const fieldType = $(this).find('.field-type-badge').data('type-id');
            console.log(`Field Card: ID=${fieldId}, Name="${fieldName}", Type=${fieldType}`);
        });
        
        // Unified notification function
        function showNotification(type, message, autoClose = true) {
            // Remove any existing notifications
            $('.notification-toast').remove();
            
            // Create notification element
            const toast = $(`<div class="notification-toast alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>`);
            
            // Add to document
            $('body').append(toast);
            
            // Position it
            toast.css({
                'position': 'fixed',
                'top': '20px',
                'right': '20px',
                'z-index': '9999',
                'min-width': '300px'
            });
            
            // Auto close after 3 seconds if requested
            if (autoClose) {
                setTimeout(() => {
                    toast.alert('close');
                }, 3000);
            }
            
            return toast;
        }
        
        // Expose notification function globally
        window.showNotification = showNotification;
        
        // Function to toggle field options based on type
        function toggleFieldOptions(fieldType) {
            // Hide all option-specific divs first
            $('.field-type-options').hide();
            
            // Show relevant options based on field type
            if (fieldType == 0 || fieldType == 1) {
                // Single or Multi Select fields
                $('#edit_field_options_container').show();
            } else {
                $('#edit_field_options_container').hide();
            }
        }
        
        // Handle field type change in edit modal
        $('#edit_field_type').on('change', function() {
            const selectedType = $(this).val();
            toggleFieldOptions(selectedType);
        });
        
        // Handle edit field form submission
        $('#edit_field_form').on('submit', function(e) {
            e.preventDefault();
            
            // Get form data and validate
            const fieldId = $('#edit_field_id').val();
            const fieldName = $('#edit_field_name').val();
            const fieldType = $('#edit_field_type').val();
            const fieldOptions = $('#edit_field_options').val();
            
            if (!fieldId || !fieldName || fieldType === undefined) {
                showNotification('danger', 'Please fill out all required fields');
                return;
            }
            
            // Show loading state
            const submitBtn = $(this).find('button[type="submit"]');
            const originalBtnText = submitBtn.html();
            submitBtn.html('<i class="fa fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
            
            // AJAX call to update field
            $.ajax({
                url: 'ajax/edit_field.php',
                type: 'POST',
                data: {
                    field_id: fieldId,
                    field_name: fieldName,
                    field_type: fieldType,
                    field_options: fieldOptions
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Edit field response:', response);
                    submitBtn.html(originalBtnText).prop('disabled', false);
                    
                    if (response.status === 'success') {
                        $('#editFieldModal').modal('hide');
                        
                        // Update the field card instead of reloading the page
                        const fieldCard = $(`.field-card[data-field-id="${fieldId}"]`);
                        if (fieldCard.length) {
                            // Update name
                            fieldCard.find('.field-name-text').text(fieldName);
                            
                            // Update type badge
                            const typeNames = {
                                '0': 'Single Select',
                                '1': 'Multi Select',
                                '2': 'Text Area',
                                '3': 'Date',
                                '4': 'Numeric',
                                '5': 'Integer',
                                '6': 'Time'
                            };
                            
                            const typeColors = {
                                '0': 'primary',
                                '1': 'success',
                                '2': 'info',
                                '3': 'warning',
                                '4': 'danger',
                                '5': 'secondary',
                                '6': 'dark'
                            };
                            
                            const newBadge = `<span class="badge badge-${typeColors[fieldType]}">${typeNames[fieldType]}</span>`;
                            fieldCard.find('.field-type-badge').data('type-id', fieldType).html(newBadge);
                            
                            // Update field preview based on type
                            // This would be more complex - for now just show notification
                            showNotification('success', 'Field updated successfully!');
                        } else {
                            // Fallback if card not found - reload after notification
                            showNotification('success', 'Field updated successfully! Reloading page...');
                            setTimeout(() => location.reload(), 1500);
                        }
                    } else {
                        showNotification('danger', 'Error updating field: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    submitBtn.html(originalBtnText).prop('disabled', false);
                    
                    console.error('AJAX Error Details:');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Response Text:', xhr.responseText);
                    
                    let errorMsg = 'Error updating field. Please try again.';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMsg = response.message;
                        }
                    } catch (e) {
                        // Parsing failed, use default message
                    }
                    
                    showNotification('danger', errorMsg);
                }
            });
        });
        
        // Inline field name editing
        $(document).on('click', '.field-name-text', function() {
            const fieldCard = $(this).closest('.card');
            const fieldId = fieldCard.data('field-id');
            const currentName = $(this).text().trim();
            
            // Replace text with input
            $(this).hide();
            const inputHtml = `<div class="inline-edit-container">
                <input type="text" class="form-control inline-field-name-edit" value="${currentName}">
                <div class="btn-group mt-2" role="group">
                    <button type="button" class="btn btn-sm btn-success save-inline-edit"><i class="fa fa-check"></i> Save</button>
                    <button type="button" class="btn btn-sm btn-secondary cancel-inline-edit"><i class="fa fa-times"></i> Cancel</button>
                </div>
            </div>`;
            
            $(this).after(inputHtml);
            fieldCard.find('.inline-field-name-edit').focus().select();
        });
        
        // Cancel inline edit
        $(document).on('click', '.cancel-inline-edit', function() {
            const container = $(this).closest('.inline-edit-container');
            const nameText = container.prev('.field-name-text');
            
            container.remove();
            nameText.show();
        });
        
        // Save inline field name edit
        $(document).on('click', '.save-inline-edit', function() {
            const container = $(this).closest('.inline-edit-container');
            const fieldCard = $(this).closest('.card');
            const fieldId = fieldCard.data('field-id');
            const nameText = container.prev('.field-name-text');
            const newName = container.find('.inline-field-name-edit').val().trim();
            
            if (!newName) {
                showNotification('warning', 'Field name cannot be empty');
                return;
            }
            
            // Show loading indicator
            const saveBtn = $(this);
            const originalBtnHtml = saveBtn.html();
            saveBtn.html('<i class="fa fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
            
            // AJAX call to update field name
            $.ajax({
                url: 'ajax/update_field_name.php',
                type: 'POST',
                data: {
                    field_id: fieldId,
                    field_name: newName
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        // Update UI
                        nameText.text(newName).show();
                        container.remove();
                        showNotification('success', 'Field name updated successfully');
                        
                        // Reload page after successful update
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        saveBtn.html(originalBtnHtml).prop('disabled', false);
                        showNotification('danger', 'Error updating field name: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    saveBtn.html(originalBtnHtml).prop('disabled', false);
                    console.error('AJAX Error:', status, error, xhr.responseText);
                    showNotification('danger', 'Failed to update field name. Please try again.');
                }
            });
        });
        
        // Add inline type editing dropdown
        $(document).on('click', '.field-type-badge', function() {
            const fieldCard = $(this).closest('.card');
            const fieldId = fieldCard.data('field-id');
            const currentType = $(this).data('type-id');
            
            // Create type options
            const typeOptions = [
                { id: 0, name: 'Single Select' },
                { id: 1, name: 'Multi Select' },
                { id: 2, name: 'Text Area' },
                { id: 3, name: 'Date' },
                { id: 4, name: 'Numeric' },
                { id: 5, name: 'Integer' },
                { id: 6, name: 'Time' }
            ];
            
            let optionsHtml = '';
            typeOptions.forEach(option => {
                const selected = option.id == currentType ? 'selected' : '';
                optionsHtml += `<option value="${option.id}" ${selected}>${option.name}</option>`;
            });
            
            // Replace badge with dropdown
            $(this).hide();
            const dropdownHtml = `<div class="inline-type-edit-container">
                <select class="form-control inline-field-type-edit">${optionsHtml}</select>
                <div class="btn-group mt-2" role="group">
                    <button type="button" class="btn btn-sm btn-success save-inline-type"><i class="fa fa-check"></i> Save</button>
                    <button type="button" class="btn btn-sm btn-secondary cancel-inline-type"><i class="fa fa-times"></i> Cancel</button>
                </div>
            </div>`;
            
            $(this).after(dropdownHtml);
        });
        
        // Cancel inline type edit
        $(document).on('click', '.cancel-inline-type', function() {
            const container = $(this).closest('.inline-type-edit-container');
            const typeBadge = container.prev('.field-type-badge');
            
            container.remove();
            typeBadge.show();
        });
        
        // Save inline field type edit
        $(document).on('click', '.save-inline-type', function() {
            const container = $(this).closest('.inline-type-edit-container');
            const fieldCard = $(this).closest('.card');
            const fieldId = fieldCard.data('field-id');
            const typeBadge = container.prev('.field-type-badge');
            const newTypeId = container.find('.inline-field-type-edit').val();
            
            // Add a debug alert to check field ID
            if (!fieldId) {
                alert("Debug: Missing field ID. Check HTML structure and data-field-id attribute.");
                console.error("Missing field ID on element:", fieldCard);
                return;
            }
            
            console.log('Updating field type - ID:', fieldId, 'New Type:', newTypeId);
            
            // Type names mapping
            const typeNames = {
                '0': 'Single Select',
                '1': 'Multi Select',
                '2': 'Text Area',
                '3': 'Date',
                '4': 'Numeric',
                '5': 'Integer',
                '6': 'Time'
            };
            
            // Badge colors mapping
            const typeColors = {
                '0': 'primary',
                '1': 'success',
                '2': 'info',
                '3': 'warning',
                '4': 'danger',
                '5': 'secondary',
                '6': 'dark'
            };
            
            // Show loading indicator
            const saveBtn = $(this);
            const originalBtnHtml = saveBtn.html();
            saveBtn.html('<i class="fa fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
            
            // Use the dedicated endpoint for field type updates
            $.ajax({
                url: 'ajax/update_field_type.php', // Updated endpoint
                type: 'POST',
                data: {
                    field_id: fieldId,
                    field_type: newTypeId
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Field type update response:', response);
                    if (response.status === 'success') {
                        // Update UI
                        const newBadge = `<span class="badge badge-${typeColors[newTypeId]}">${typeNames[newTypeId]}</span>`;
                        typeBadge.data('type-id', newTypeId).html(newBadge).show();
                        container.remove();
                        showNotification('success', 'Field type updated successfully');
                        
                        // Reload page after successful update
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        saveBtn.html(originalBtnHtml).prop('disabled', false);
                        showNotification('danger', 'Error updating field type: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    saveBtn.html(originalBtnHtml).prop('disabled', false);
                    console.error('AJAX Error Details for field type update:');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Response Text:', xhr.responseText);
                    
                    let errorMsg = 'Error updating field type. Please try again.';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMsg = response.message;
                        }
                    } catch (e) {
                        // Parsing failed, use default message
                        console.error('Failed to parse error response:', e);
                    }
                    
                    showNotification('danger', errorMsg);
                }
            });
        });
    });
}); 