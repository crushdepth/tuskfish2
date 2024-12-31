document.addEventListener('DOMContentLoaded', () => {
    const typeField = document.getElementById('type');
    const templateField = document.getElementById('template');

    function updateTemplateOptions() {
      const selectedType = typeField.value;
      templateField.innerHTML = ''; // Clear all existing options

      const typeTemplates = templates[selectedType] || {};
      for (const [value, label] of Object.entries(typeTemplates)) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        templateField.appendChild(option);
      }
    }

    typeField.addEventListener('change', updateTemplateOptions);
    updateTemplateOptions();
  });
