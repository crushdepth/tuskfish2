document.addEventListener('DOMContentLoaded', () => {
    const typeField = document.getElementById('type');
    const templateField = document.getElementById('template');
    const templates = <?php echo json_encode($viewModel->blockTemplates(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

    function updateTemplateOptions() {
        const selectedType = typeField.value;
        const typeTemplates = templates[selectedType] || {};

        // Clear the template dropdown
        templateField.innerHTML = '<option value="" disabled selected><?php echo TFISH_SELECT_TEMPLATE; ?></option>';

        // Populate the template dropdown
        for (const [value, label] of Object.entries(typeTemplates)) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            templateField.appendChild(option);
        }
    }

    // Attach event listener for type changes
    typeField.addEventListener('change', updateTemplateOptions);
});
