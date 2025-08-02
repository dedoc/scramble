document.addEventListener("DOMContentLoaded", () => {
        initializeAutosuggest();
    });
    

function initializeAutosuggest() {

    const docs = document.getElementById('docs');
    
    const searchInput = document.getElementById('endpoint-search');
    const suggestionsList = document.getElementById('suggestions');
    
    const paths = docs.apiDescriptionDocument.paths;
    const endpoints = Object.entries(paths).map(([path, methods]) => ({
        path,
        methods: Object.keys(methods),
        operationId: methods[Object.keys(methods)[0]].operationId
    }));

    searchInput.addEventListener('input', (e) => {
        const value = e.target.value.toLowerCase();
        if (!value) {
            suggestionsList.style.display = 'none';
            return;
        }

        const filtered = endpoints.filter(endpoint => 
            endpoint.path.toLowerCase().includes(value)
        );

        suggestionsList.innerHTML = filtered
            .map(endpoint => `
                <div class="suggestion-item" onclick="navigateToEndpoint('${endpoint.operationId}')">
                    ${endpoint.path} (${endpoint.methods.join(', ').toUpperCase()})
                </div>
            `)
            .join('');

        suggestionsList.style.display = filtered.length ? 'block' : 'none';
    });

    // Close suggestions when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.autosuggest-box')) {
            suggestionsList.style.display = 'none';
        }
    });

    // Add keyboard shortcut handler
    document.addEventListener('keydown', (e) => {
        // Check for Command+F (Mac) or Ctrl+F (Windows/Linux)
        if ((e.metaKey || e.ctrlKey) && e.key === 'f') {
            e.preventDefault(); // Prevent default browser find
            searchInput.focus();
        }
    });
}