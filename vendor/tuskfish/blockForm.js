document.addEventListener('DOMContentLoaded', () => {
    const typeField = document.getElementById('type');
    const templateField = document.getElementById('template');
    const templates = JSON.parse(document.getElementById('blockTemplates').textContent);
  
    // Function to update the template options
    const updateTemplates = () => {
      const selectedType = typeField.value;
      const options = templates[selectedType] || {};
  
      // Clear current options
      templateField.innerHTML = '';
  
      // Handle cases with no options
      const keys = Object.keys(options);
      if (keys.length === 0) {
        templateField.parentElement.style.display = 'block'; // Ensure the field is visible
        return;
      }
  
      // If only one option, auto-select it and hide the field
      if (keys.length === 1) {
        const key = keys[0];
        templateField.innerHTML = `<option value="${key}" selected>${options[key]}</option>`;
        templateField.parentElement.style.display = 'none'; // Hide field
        return;
      }
  
      // Add a placeholder and multiple options
      templateField.innerHTML = '<option value="" disabled selected>Select a template</option>';
      for (const [key, label] of Object.entries(options)) {
        templateField.innerHTML += `<option value="${key}">${label}</option>`;
      }
      templateField.parentElement.style.display = 'block'; // Ensure the field is visible
    };
  
    // Clear the template field on page load
    templateField.innerHTML = '';
    templateField.parentElement.style.display = 'block';
  
    // Attach event listener to the type field
    typeField.addEventListener('change', updateTemplates);
  });
  