document.addEventListener('DOMContentLoaded', () => {
    const typeField = document.getElementById('type');
    const templateField = document.getElementById('template');

    function updateTemplateOptions() {
        const selectedType = typeField.value; // Get the selected block type.

        // Preserve the current selection before clearing. On the edit form this is the saved
        // template rendered server-side; reapplying it below keeps a non-first template (e.g. a
        // custom 'featured-content' template) selected instead of defaulting to the first option.
        // When the type changes the old value is absent from the new list, so it harmlessly drops.
        const selectedTemplate = templateField.value;

        templateField.innerHTML = ''; // Clear existing options.

        // Get the templates for the selected block type.
        const typeTemplates = templates[selectedType] || {};
        for (const [value, label] of Object.entries(typeTemplates)) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            if (value === selectedTemplate) {
                option.selected = true;
            }
            templateField.appendChild(option);
        }
    }

    // Event listener for type dropdown changes.
    typeField.addEventListener('change', () => {
        updateTemplateOptions(); // Update the template dropdown
    });

    // Initialize the template dropdown on page load.
    updateTemplateOptions();
});
