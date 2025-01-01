document.addEventListener('DOMContentLoaded', () => {
    const typeField = document.getElementById('type');
    const templateField = document.getElementById('template');
    const configContainer = document.querySelector('#config-container');

    // const blockConfigTemplates must be initialised in the HTML template.

    function updateTemplateOptions() {
        const selectedType = typeField.value; // Get the selected block type
        templateField.innerHTML = ''; // Clear existing options

        // Get the templates for the selected block type
        const typeTemplates = templates[selectedType] || {};
        for (const [value, label] of Object.entries(typeTemplates)) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            templateField.appendChild(option);
        }
    }

    function updateConfigTemplate() {
        const selectedType = typeField.value || '\\Tfish\\Content\\Block\\Html';
        const templateContent = blockConfigTemplates[selectedType] || '<p>Template not available.</p>';

        // Replace the config-container content
        configContainer.innerHTML = templateContent;
    }

    // Event listener for type dropdown changes
    typeField.addEventListener('change', () => {
        updateTemplateOptions(); // Update the template dropdown
        updateConfigTemplate(); // Update the configuration section
    });

    // Initialize both the template dropdown and the config section on page load
    updateTemplateOptions();
    updateConfigTemplate();
});
