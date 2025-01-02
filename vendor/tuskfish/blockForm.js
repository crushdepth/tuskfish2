document.addEventListener('DOMContentLoaded', () => {
    const typeField = document.getElementById('type');
    const templateField = document.getElementById('template');

    function updateTemplateOptions() {
        const selectedType = typeField.value; // Get the selected block type.
        templateField.innerHTML = ''; // Clear existing options.

        // Get the templates for the selected block type.
        const typeTemplates = templates[selectedType] || {};
        for (const [value, label] of Object.entries(typeTemplates)) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
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
