document.addEventListener('DOMContentLoaded', () => {
    // Function to update the 'template' select box options
    function updateTemplateOptions() {
        const typeField = document.getElementById('type'); // Type dropdown
        const templateField = document.getElementById('template'); // Template dropdown
        const selectedType = typeField.value; // Get selected 'type'

        // Clear the current template options
        templateField.innerHTML = '';

        // Get the templates for the selected type
        const typeTemplates = templates[selectedType] || {};

        // Populate the template dropdown
        for (const [value, label] of Object.entries(typeTemplates)) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            templateField.appendChild(option);
        }
    }

    // Attach the function to the onchange event of the 'type' dropdown
    const typeField = document.getElementById('type');
    if (typeField) {
        typeField.addEventListener('change', updateTemplateOptions);
    }
});
