// app-protexa-seguro/assets/js/app.js - VERSIÓN COMPLETA

/**
 * Protexa Seguro App - JavaScript Principal
 * Sistema de gestión de recorridos de seguridad
 */

class ProtexaApp {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = window.tourData ? window.tourData.totalSteps : 7;
        this.tourData = {};
        this.photos = {};
        this.isDraft = false;
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.checkConnection();
        this.loadDraftData();
        this.setupFormValidation();
        this.setupPhotoUpload();
        this.setupAutoSave();
    }

    setupEventListeners() {
        // Navigation buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('.nav-next')) {
                this.nextStep();
            }
            if (e.target.matches('.nav-prev')) {
                this.prevStep();
            }
            if (e.target.matches('.nav-save-draft')) {
                this.saveDraft();
            }
            if (e.target.matches('.nav-submit')) {
                this.submitTour();
            }
        });

        // Form changes
        document.addEventListener('change', (e) => {
            if (e.target.matches('input[type="radio"]')) {
                this.handleAnswerChange(e.target);
            }
            if (e.target.matches('textarea')) {
                this.handleCommentChange(e.target);
            }
        });

        // Photo upload
        document.addEventListener('change', (e) => {
            if (e.target.matches('input[type="file"]')) {
                this.handlePhotoUpload(e.target);
            }
        });

        // Remove photo
        document.addEventListener('click', (e) => {
            if (e.target.matches('.photo-preview-remove')) {
                this.removePhoto(e.target);
            }
        });

        // Form submission loading
        document.addEventListener('submit', (e) => {
            const submitBtn = e.target.querySelector('button[type="submit"]');
            if (submitBtn) {
                this.showButtonLoading(submitBtn);
            }
        });
    }

    checkConnection() {
        const updateConnectionStatus = () => {
            const indicator = document.getElementById('offline-indicator');
            if (navigator.onLine) {
                if (indicator) indicator.style.display = 'none';
                this.syncPendingData();
            } else {
                if (indicator) indicator.style.display = 'flex';
            }
        };

        window.addEventListener('online', updateConnectionStatus);
        window.addEventListener('offline', updateConnectionStatus);
        updateConnectionStatus();
    }

    // Wizard Navigation
    nextStep() {
        if (this.validateCurrentStep()) {
            this.saveCurrentStepData();
            
            if (this.currentStep < this.totalSteps) {
                this.currentStep++;
                this.updateWizardView();
            } else {
                this.showSummary();
            }
        }
    }

    prevStep() {
        if (this.currentStep > 1) {
            this.saveCurrentStepData();
            this.currentStep--;
            this.updateWizardView();
        }
    }

    updateWizardView() {
        // Update progress indicators
        this.updateProgressBar();
        
        // Show/hide wizard steps
        const steps = document.querySelectorAll('.wizard-step');
        steps.forEach((step, index) => {
            if (index + 1 === this.currentStep) {
                step.style.display = 'block';
                step.classList.add('fade-in');
            } else {
                step.style.display = 'none';
                step.classList.remove('fade-in');
            }
        });

        // Update navigation buttons
        this.updateNavigationButtons();
        
        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    updateProgressBar() {
        const progressSteps = document.querySelectorAll('.progress-step');
        const progressConnectors = document.querySelectorAll('.progress-connector');
        
        progressSteps.forEach((step, index) => {
            const stepNumber = index + 1;
            step.classList.remove('active', 'completed');
            
            if (stepNumber < this.currentStep) {
                step.classList.add('completed');
                step.innerHTML = '✓';
            } else if (stepNumber === this.currentStep) {
                step.classList.add('active');
                step.innerHTML = stepNumber;
            } else {
                step.innerHTML = stepNumber;
            }
        });

        progressConnectors.forEach((connector, index) => {
            if (index + 1 < this.currentStep) {
                connector.classList.add('completed');
            } else {
                connector.classList.remove('completed');
            }
        });
    }

    updateNavigationButtons() {
        const prevBtn = document.querySelector('.nav-prev');
        const nextBtn = document.querySelector('.nav-next');
        const submitBtn = document.querySelector('.nav-submit');

        if (prevBtn) {
            prevBtn.style.display = this.currentStep > 1 ? 'block' : 'none';
        }

        if (this.currentStep < this.totalSteps) {
            if (nextBtn) nextBtn.style.display = 'block';
            if (submitBtn) submitBtn.style.display = 'none';
        } else {
            if (nextBtn) nextBtn.style.display = 'none';
            if (submitBtn) submitBtn.style.display = 'block';
        }
    }

    validateCurrentStep() {
        const currentStepElement = document.querySelector(`.wizard-step[data-step="${this.currentStep}"]`);
        if (!currentStepElement) return true;

        const requiredInputs = currentStepElement.querySelectorAll('input[required], select[required]');
        let isValid = true;

        requiredInputs.forEach(input => {
            if (input.type === 'radio') {
                const radioGroup = currentStepElement.querySelectorAll(`input[name="${input.name}"]`);
                const isChecked = Array.from(radioGroup).some(radio => radio.checked);
                if (!isChecked) {
                    this.showFieldError(input, 'Por favor selecciona una respuesta');
                    isValid = false;
                }
            } else if (!input.value.trim()) {
                this.showFieldError(input, 'Este campo es requerido');
                isValid = false;
            }
        });

        return isValid;
    }

    showFieldError(field, message) {
        this.removeFieldError(field);
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.textContent = message;
        errorDiv.style.color = 'var(--danger-color)';
        errorDiv.style.fontSize = '0.875rem';
        errorDiv.style.marginTop = '0.25rem';
        
        field.parentNode.appendChild(errorDiv);
        
        // Remove error after 5 seconds
        setTimeout(() => this.removeFieldError(field), 5000);
    }

    removeFieldError(field) {
        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
    }

    // Data Management
    saveCurrentStepData() {
        const stepData = this.getCurrentStepData();
        this.tourData[`step_${this.currentStep}`] = stepData;
        
        // Auto-save to localStorage
        this.saveDraftToStorage();
    }

    getCurrentStepData() {
        const currentStepElement = document.querySelector(`.wizard-step[data-step="${this.currentStep}"]`);
        if (!currentStepElement) return {};

        const data = {};
        
        // Get radio button values
        const radioInputs = currentStepElement.querySelectorAll('input[type="radio"]:checked');
        radioInputs.forEach(radio => {
            data[radio.name] = radio.value;
        });

        // Get textarea values
        const textareas = currentStepElement.querySelectorAll('textarea');
        textareas.forEach(textarea => {
            if (textarea.value.trim()) {
                data[textarea.name] = textarea.value;
            }
        });

        return data;
    }

    loadDraftData() {
        const draftData = localStorage.getItem('protexa_tour_draft');
        if (draftData) {
            try {
                const parsedData = JSON.parse(draftData);
                this.tourData = parsedData.tourData || {};
                this.photos = parsedData.photos || {};
                this.isDraft = true;
                
                // Show draft notification
                showToast('Se cargó un borrador guardado anteriormente', 'info');
                
                // Restore form data
                this.restoreFormData();
            } catch (error) {
                console.error('Error loading draft:', error);
                localStorage.removeItem('protexa_tour_draft');
            }
        }
    }

    saveDraftToStorage() {
        const draftData = {
            tourData: this.tourData,
            photos: this.photos,
            currentStep: this.currentStep,
            timestamp: new Date().toISOString()
        };
        
        localStorage.setItem('protexa_tour_draft', JSON.stringify(draftData));
    }

    async saveDraft() {
        console.log('🔄 Iniciando guardado de borrador...');
        
        if (!window.tourData || !window.tourData.id) {
            console.error('❌ No se encontró tour_id');
            showToast('Error: No se pudo identificar el recorrido', 'error');
            return false;
        }
        
        const tourId = window.tourData.id;
        console.log(`📝 Guardando borrador para tour_id: ${tourId}`);
        
        // Mostrar loading en el botón
        const saveBtn = document.querySelector('.nav-save-draft');
        if (saveBtn) {
            this.showButtonLoading(saveBtn);
        }
        
        try {
            // Recopilar datos del formulario actual
            this.saveCurrentStepData();
            const formData = this.collectFormData();
            console.log('📊 Datos recopilados:', formData);
            
            // Preparar payload para la API
            const payload = {
                tour_id: tourId,
                tourData: formData,
                isDraft: true,
                currentStep: this.currentStep,
                timestamp: new Date().toISOString()
            };
            
            console.log('📤 Enviando payload:', payload);
            
            // Hacer request a la API
            const response = await fetch('api/save-progress.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });
            
            console.log(`📡 Response status: ${response.status}`);
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('❌ Error response:', errorText);
                
                if (response.status === 401) {
                    showToast('Sesión expirada. Redirigiendo al login...', 'warning');
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 2000);
                    return false;
                }
                
                throw new Error(`HTTP ${response.status}: ${errorText}`);
            }
            
            const result = await response.json();
            console.log('✅ Respuesta exitosa:', result);
            
            if (result.success) {
                showToast(result.message || 'Borrador guardado correctamente', 'success');
                
                // Guardar también en localStorage como backup
                this.saveDraftToStorage();
                
                return true;
            } else {
                throw new Error(result.message || 'Error desconocido al guardar');
            }
            
        } catch (error) {
            console.error('❌ Error guardando borrador:', error);
            
            // Guardar en localStorage como fallback
            this.saveDraftToStorage();
            
            if (navigator.onLine) {
                showToast('Error al guardar en servidor. Guardado localmente.', 'warning');
            } else {
                showToast('Sin conexión. Guardado localmente.', 'info');
            }
            
            return false;
            
        } finally {
            // Ocultar loading
            if (saveBtn) {
                this.hideButtonLoading(saveBtn);
            }
        }
    }

    collectFormData() {
        const formData = {};
        const form = document.getElementById('tour-form');
        
        if (!form) {
            console.warn('⚠️ No se encontró el formulario');
            return formData;
        }
        
        // Obtener todas las respuestas de radio buttons
        const radioButtons = form.querySelectorAll('input[type="radio"]:checked');
        radioButtons.forEach(radio => {
            const stepKey = `step_${this.currentStep}`;
            if (!formData[stepKey]) {
                formData[stepKey] = {};
            }
            formData[stepKey][radio.name] = radio.value;
        });
        
        // Obtener comentarios de categorías
        const textareas = form.querySelectorAll('textarea[name^="category_comments"]');
        textareas.forEach(textarea => {
            if (textarea.value.trim()) {
                const stepKey = `step_${this.currentStep}`;
                if (!formData[stepKey]) {
                    formData[stepKey] = {};
                }
                formData[stepKey][textarea.name] = textarea.value;
            }
        });
        
        // Obtener comentarios finales
        const finalComments = form.querySelector('textarea[name="final_comments"]');
        if (finalComments && finalComments.value.trim()) {
            formData['final_comments'] = finalComments.value;
        }
        
        // Obtener prioridad crítica
        const priorityLevel = form.querySelector('input[name="priority_level"]:checked');
        if (priorityLevel) {
            formData['priority_level'] = priorityLevel.value;
        }
        
        // Obtener descripción crítica
        const criticalDesc = form.querySelector('textarea[name="critical_description"]');
        if (criticalDesc && criticalDesc.value.trim()) {
            formData['critical_description'] = criticalDesc.value;
        }
        
        console.log('📋 Form data collected:', formData);
        return formData;
    }

    restoreFormData() {
        Object.keys(this.tourData).forEach(stepKey => {
            const stepData = this.tourData[stepKey];
            Object.keys(stepData).forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    if (field.type === 'radio') {
                        const radioOption = document.querySelector(`[name="${fieldName}"][value="${stepData[fieldName]}"]`);
                        if (radioOption) radioOption.checked = true;
                    } else {
                        field.value = stepData[fieldName];
                    }
                }
            });
        });
    }

    // Photo Management
    setupPhotoUpload() {
        document.addEventListener('click', (e) => {
            if (e.target.matches('.photo-upload') || e.target.closest('.photo-upload')) {
                const fileInput = e.target.querySelector('input[type="file"]') || 
                                e.target.closest('.photo-upload').querySelector('input[type="file"]');
                if (fileInput) {
                    fileInput.click();
                }
            }
        });
    }

    handlePhotoUpload(input) {
        const files = Array.from(input.files);
        const categoryId = input.dataset.categoryId || `step_${this.currentStep}`;
        
        if (!this.photos[categoryId]) {
            this.photos[categoryId] = [];
        }

        files.forEach(file => {
            if (this.validatePhoto(file)) {
                this.processPhoto(file, categoryId);
            }
        });
    }

    validatePhoto(file) {
        const maxSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        
        if (!allowedTypes.includes(file.type)) {
            showToast('Solo se permiten imágenes JPG, JPEG y PNG', 'error');
            return false;
        }
        
        if (file.size > maxSize) {
            showToast('La imagen no puede ser mayor a 5MB', 'error');
            return false;
        }
        
        return true;
    }

    processPhoto(file, categoryId) {
        const reader = new FileReader();
        
        reader.onload = (e) => {
            const photoData = {
                id: this.generatePhotoId(),
                file: file,
                dataUrl: e.target.result,
                name: file.name,
                size: file.size,
                timestamp: new Date().toISOString()
            };
            
            this.photos[categoryId].push(photoData);
            this.updatePhotoPreview(categoryId);
            this.saveDraftToStorage();
        };
        
        reader.readAsDataURL(file);
    }

    generatePhotoId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    }

    updatePhotoPreview(categoryId) {
        const previewContainer = document.querySelector(`[data-category-id="${categoryId}"] .photo-preview`);
        if (!previewContainer) return;

        previewContainer.innerHTML = '';
        
        const photos = this.photos[categoryId] || [];
        photos.forEach(photo => {
            const previewItem = this.createPhotoPreviewElement(photo, categoryId);
            previewContainer.appendChild(previewItem);
        });
    }

    createPhotoPreviewElement(photo, categoryId) {
        const div = document.createElement('div');
        div.className = 'photo-preview-item';
        div.innerHTML = `
            <img src="${photo.dataUrl}" alt="${photo.name}">
            <button type="button" class="photo-preview-remove" 
                    data-photo-id="${photo.id}" 
                    data-category-id="${categoryId}">×</button>
        `;
        return div;
    }

    removePhoto(button) {
        const photoId = button.dataset.photoId;
        const categoryId = button.dataset.categoryId;
        
        if (this.photos[categoryId]) {
            this.photos[categoryId] = this.photos[categoryId].filter(photo => photo.id !== photoId);
            this.updatePhotoPreview(categoryId);
            this.saveDraftToStorage();
        }
    }

    // Form Handlers
    handleAnswerChange(input) {
        this.removeFieldError(input);
        
        // Auto-save progress
        this.saveCurrentStepData();
        
        // Show conditional fields based on answer
        this.handleConditionalFields(input);
    }

    handleCommentChange(textarea) {
        // Auto-resize textarea
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
        
        // Auto-save
        clearTimeout(this.commentTimeout);
        this.commentTimeout = setTimeout(() => {
            this.saveCurrentStepData();
        }, 1000);
    }

    handleConditionalFields(input) {
        // Show photo upload for "No" answers
        if (input.value === 'no') {
            const photoUpload = input.closest('.question-card').querySelector('.photo-upload');
            if (photoUpload) {
                photoUpload.style.display = 'block';
            }
        }
    }

    // Auto-save functionality
    setupAutoSave() {
        let lastSaveData = null;
        setInterval(() => {
            const currentData = JSON.stringify(this.collectFormData());
            if (currentData !== lastSaveData && currentData !== '{}') {
                console.log('🔄 Auto-guardado iniciado...');
                this.saveDraft();
                lastSaveData = currentData;
            }
        }, 120000); // 2 minutos
    }

    async sendDraftToServer() {
        try {
            const response = await fetch('api/save-progress.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    tour_id: window.tourData.id,
                    tourData: this.tourData,
                    photos: this.photos,
                    currentStep: this.currentStep,
                    isDraft: true
                })
            });

            const result = await response.json();

            if (result.success) {
                console.log('Draft saved to server');
            }
        } catch (error) {
            console.error('Error saving draft to server:', error);
        }
    }

    // Offline functionality
    async syncPendingData() {
        const pendingData = localStorage.getItem('protexa_pending_sync');
        if (pendingData) {
            try {
                const data = JSON.parse(pendingData);
                const response = await fetch('api/save-progress.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(data)
                });

                if (response.ok) {
                    localStorage.removeItem('protexa_pending_sync');
                    showToast('Datos sincronizados correctamente', 'success');
                }
            } catch (error) {
                console.error('Error syncing data:', error);
            }
        }
    }

    // Form submission
    async submitTour() {
        this.saveCurrentStepData();
        
        if (!this.validateAllSteps()) {
            showToast('Por favor completa todas las secciones requeridas', 'error');
            return;
        }

        const submitBtn = document.querySelector('.nav-submit');
        this.showButtonLoading(submitBtn);

        try {
            const formData = new FormData();
            
            // Add tour data
            formData.append('tour_id', window.tourData.id);
            formData.append('tourData', JSON.stringify(this.tourData));
            formData.append('isDraft', 'false');
            
            // Add photos
            Object.keys(this.photos).forEach(categoryId => {
                this.photos[categoryId].forEach((photo, index) => {
                    if (photo.file) {
                        formData.append(`photos[${categoryId}][${index}]`, photo.file);
                    }
                });
            });

            const response = await fetch('api/save-progress.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    // Clear draft
                    localStorage.removeItem('protexa_tour_draft');
                    
                    // Redirect to success page
                    window.location.href = result.redirect_url || 'success.php';
                } else {
                    throw new Error(result.message || 'Error en el servidor');
                }
            } else {
                throw new Error('Error en la respuesta del servidor');
            }
        } catch (error) {
            console.error('Submission error:', error);
            
            if (!navigator.onLine) {
                // Save for later sync
                localStorage.setItem('protexa_pending_sync', JSON.stringify({
                    tour_id: window.tourData.id,
                    tourData: this.tourData,
                    photos: this.photos,
                    isDraft: false
                }));
                showToast('Sin conexión. Se guardará cuando vuelva la conexión.', 'warning');
            } else {
                showToast('Error al enviar el recorrido. Inténtalo de nuevo.', 'error');
            }
        } finally {
            this.hideButtonLoading(submitBtn);
        }
    }

    validateAllSteps() {
        // Implement comprehensive validation
        return true; // Simplified for now
    }

    // UI Helpers
    showButtonLoading(button) {
        if (!button) return;
        
        const text = button.querySelector('.btn-text');
        const loading = button.querySelector('.btn-loading');
        
        if (text) text.style.display = 'none';
        if (loading) loading.style.display = 'flex';
        
        button.disabled = true;
    }

    hideButtonLoading(button) {
        if (!button) return;
        
        const text = button.querySelector('.btn-text');
        const loading = button.querySelector('.btn-loading');
        
        if (text) text.style.display = 'block';
        if (loading) loading.style.display = 'none';
        
        button.disabled = false;
    }

    setupFormValidation() {
        // Real-time validation
        document.addEventListener('blur', (e) => {
            if (e.target.matches('input[required], select[required], textarea[required]')) {
                this.validateField(e.target);
            }
        }, true);
    }

    validateField(field) {
        this.removeFieldError(field);
        
        if (field.hasAttribute('required') && !field.value.trim()) {
            this.showFieldError(field, 'Este campo es requerido');
            return false;
        }
        
        return true;
    }

    // Summary and completion
    showSummary() {
        // Implementation for showing summary before submission
        const summaryData = this.generateSummaryData();
        this.renderSummary(summaryData);
    }

    generateSummaryData() {
        const summary = {
            totalQuestions: 0,
            answeredQuestions: 0,
            yesAnswers: 0,
            noAnswers: 0,
            naAnswers: 0,
            categories: {}
        };

        Object.keys(this.tourData).forEach(stepKey => {
            const stepData = this.tourData[stepKey];
            Object.keys(stepData).forEach(key => {
                if (key.includes('question_')) {
                    summary.totalQuestions++;
                    const answer = stepData[key];
                    if (answer) {
                        summary.answeredQuestions++;
                        switch(answer) {
                            case 'si': summary.yesAnswers++; break;
                            case 'no': summary.noAnswers++; break;
                            case 'na': summary.naAnswers++; break;
                        }
                    }
                }
            });
        });

        return summary;
    }

    renderSummary(summaryData) {
        // Create summary view
        const summaryHTML = `
            <div class="summary-card">
                <div class="summary-header">
                    <h2>Resumen del Recorrido</h2>
                    <div class="summary-badge ${window.tourData.type || 'scheduled'}">${window.tourData.type === 'emergency' ? 'Emergencia' : 'Programado'}</div>
                </div>
                
                <div class="summary-stats">
                    <div class="summary-stat">
                        <span class="summary-stat-value">${summaryData.answeredQuestions}/${summaryData.totalQuestions}</span>
                        <span class="summary-stat-label">Completado</span>
                    </div>
                    <div class="summary-stat">
                        <span class="summary-stat-value">${summaryData.yesAnswers}</span>
                        <span class="summary-stat-label">Sí</span>
                    </div>
                    <div class="summary-stat">
                        <span class="summary-stat-value">${summaryData.noAnswers}</span>
                        <span class="summary-stat-label">No</span>
                    </div>
                    <div class="summary-stat">
                        <span class="summary-stat-value">${summaryData.naAnswers}</span>
                        <span class="summary-stat-label">N/A</span>
                    </div>
                </div>
                
                <p>Revisa los datos antes de enviar el recorrido.</p>
            </div>
        `;
        
        // Insert summary
        const container = document.querySelector('.wizard-container');
        if (container) {
            container.innerHTML = summaryHTML + container.innerHTML;
        }
    }

    // Utility functions
    formatDate(date) {
        return new Intl.DateTimeFormat('es-MX', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        }).format(new Date(date));
    }

    generateId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Camera utilities
class CameraHelper {
    static async initCamera() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { 
                    facingMode: 'environment',
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                }
            });
            return stream;
        } catch (error) {
            console.error('Error accessing camera:', error);
            throw error;
        }
    }

    static stopCamera(stream) {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
    }

    static captureImage(videoElement) {
        const canvas = document.createElement('canvas');
        canvas.width = videoElement.videoWidth;
        canvas.height = videoElement.videoHeight;
        
        const ctx = canvas.getContext('2d');
        ctx.drawImage(videoElement, 0, 0);
        
        return new Promise(resolve => {
            canvas.toBlob(resolve, 'image/jpeg', 0.8);
        });
    }
}

// Local storage utilities
class StorageHelper {
    static setItem(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (error) {
            console.error('Error saving to localStorage:', error);
            return false;
        }
    }

    static getItem(key) {
        try {
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : null;
        } catch (error) {
            console.error('Error reading from localStorage:', error);
            return null;
        }
    }

    static removeItem(key) {
        try {
            localStorage.removeItem(key);
            return true;
        } catch (error) {
            console.error('Error removing from localStorage:', error);
            return false;
        }
    }

    static clear() {
        try {
            localStorage.clear();
            return true;
        } catch (error) {
            console.error('Error clearing localStorage:', error);
            return false;
        }
    }
}

// Form utilities
class FormHelper {
    static serializeForm(form) {
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            if (data[key]) {
                if (Array.isArray(data[key])) {
                    data[key].push(value);
                } else {
                    data[key] = [data[key], value];
                }
            } else {
                data[key] = value;
            }
        }
        
        return data;
    }

    static populateForm(form, data) {
        Object.keys(data).forEach(key => {
            const field = form.querySelector(`[name="${key}"]`);
            if (field) {
                if (field.type === 'radio') {
                    const radio = form.querySelector(`[name="${key}"][value="${data[key]}"]`);
                    if (radio) radio.checked = true;
                } else if (field.type === 'checkbox') {
                    field.checked = data[key];
                } else {
                    field.value = data[key];
                }
            }
        });
    }

    static validateForm(form) {
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        let isValid = true;

        inputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('error');
                isValid = false;
            } else {
                input.classList.remove('error');
            }
        });

        return isValid;
    }
}

// Network utilities
class NetworkHelper {
    static async checkConnection() {
        if (!navigator.onLine) return false;
        
        try {
            const response = await fetch('api/ping.php', {
                method: 'HEAD',
                cache: 'no-cache'
            });
            return response.ok;
        } catch {
            return false;
        }
    }

    static async retryRequest(requestFn, maxRetries = 3, delay = 1000) {
        for (let i = 0; i < maxRetries; i++) {
            try {
                return await requestFn();
            } catch (error) {
                if (i === maxRetries - 1) throw error;
                await new Promise(resolve => setTimeout(resolve, delay * Math.pow(2, i)));
            }
        }
    }
}

// Global Utility Functions
function showToast(message, type = 'info', duration = 4000) {
    const container = document.getElementById('toast-container') || createToastContainer();
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const icon = {
        'success': '✅',
        'error': '❌', 
        'warning': '⚠️',
        'info': 'ℹ️'
    };
    
    toast.innerHTML = `
        <span class="toast-icon">${icon[type] || icon.info}</span>
        <span class="toast-message">${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">×</button>
    `;
    
    container.appendChild(toast);
    
    // Auto remove
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, duration);
}

function createToastContainer() {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    return container;
}

function showLoading(message = 'Cargando...') {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        const text = overlay.querySelector('p');
        if (text) text.textContent = message;
        overlay.style.display = 'flex';
    }
}

function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

// Menu Functions
function toggleMenu() {
    const menu = document.getElementById('headerMenu');
    if (!menu) return;
    
    const isOpen = menu.classList.contains('active');
    
    if (isOpen) {
        menu.classList.remove('active');
        document.removeEventListener('click', closeMenuOnOutsideClick);
    } else {
        menu.classList.add('active');
        setTimeout(() => {
            document.addEventListener('click', closeMenuOnOutsideClick);
        }, 10);
    }
}

function closeMenuOnOutsideClick(event) {
    const menu = document.getElementById('headerMenu');
    const toggle = document.querySelector('.menu-toggle');
    
    if (menu && toggle && !menu.contains(event.target) && !toggle.contains(event.target)) {
        menu.classList.remove('active');
        document.removeEventListener('click', closeMenuOnOutsideClick);
    }
}

function initializeMenu() {
    // Marcar item activo del menú
    const currentPage = window.location.pathname.split('/').pop();
    const menuItems = document.querySelectorAll('.menu-item');
    
    menuItems.forEach(item => {
        const href = item.getAttribute('href');
        if (href && href.includes(currentPage)) {
            item.classList.add('active');
        }
    });
}

// PWA Functions
let deferredPrompt;

function showInstallBanner() {
    if (deferredPrompt && !localStorage.getItem('pwa-dismissed-' + new Date().toDateString())) {
        showToast(
            '¡Instala Protexa Seguro para acceso rápido! <button onclick="installPWA()" style="margin-left: 10px; padding: 2px 8px; border: none; background: white; border-radius: 3px;">Instalar</button>',
            'info',
            8000
        );
    }
}

async function installPWA() {
    if (deferredPrompt) {
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        deferredPrompt = null;
        
        if (outcome === 'accepted') {
            showToast('¡Aplicación instalada correctamente!', 'success');
        } else {
            localStorage.setItem('pwa-dismissed-' + new Date().toDateString(), 'true');
        }
    }
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Protexa Seguro App iniciando...');
    
    // Only initialize wizard on recorrido page
    if (document.querySelector('.wizard-container')) {
        console.log('📋 Inicializando wizard de recorrido...');
        window.protexaApp = new ProtexaApp();
    }

    // Initialize PWA features
    if ('serviceWorker' in navigator && window.location.protocol === 'https:') {
        navigator.serviceWorker.register('sw.js')
            .then(registration => {
                console.log('SW registered:', registration);
                
                // Verificar actualizaciones cada 5 minutos
                setInterval(() => {
                    registration.update().catch(error => {
                        console.warn('Error verificando actualizaciones del SW:', error);
                    });
                }, 300000);
            })
            .catch(error => {
                console.log('SW registration failed:', error);
            });
    }

    // Handle beforeinstallprompt
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        
        // Solo mostrar prompt en páginas principales
        const currentPage = window.location.pathname.split('/').pop();
        const mainPages = ['index.php', 'dashboard.php', ''];
        
        if (mainPages.includes(currentPage)) {
            setTimeout(showInstallBanner, 10000); // Mostrar después de 10 segundos
        }
    });

    // Initialize menu
    initializeMenu();
    
    // Connection status
    window.addEventListener('online', () => {
        const indicator = document.getElementById('offline-indicator');
        if (indicator) indicator.style.display = 'none';
        showToast('Conexión restaurada', 'success');
    });

    window.addEventListener('offline', () => {
        const indicator = document.getElementById('offline-indicator');
        if (indicator) indicator.style.display = 'flex';
        showToast('Sin conexión - Trabajando offline', 'warning');
    });

    // Verificar estado inicial de conexión
    if (!navigator.onLine) {
        const indicator = document.getElementById('offline-indicator');
        if (indicator) indicator.style.display = 'flex';
    }

    // Mostrar notificación de bienvenida si es primera visita al dashboard
    if (window.location.pathname.includes('dashboard.php') && !localStorage.getItem('dashboard_visited')) {
        setTimeout(() => {
            showToast('¡Bienvenido a Protexa Seguro! Selecciona un tipo de recorrido para comenzar.', 'info', 6000);
            localStorage.setItem('dashboard_visited', 'true');
        }, 1000);
    }

    console.log('✅ Protexa Seguro App inicializada correctamente');
});

// Funciones adicionales para eventos específicos

// Event listener para el botón de guardar borrador (si no está en wizard)
document.addEventListener('click', function(e) {
    if (e.target.matches('.nav-save-draft') && !window.protexaApp) {
        e.preventDefault();
        
        // Crear instancia temporal para guardar
        const tempApp = new ProtexaApp();
        tempApp.saveDraft();
    }
});

// Auto-resize textareas globalmente
document.addEventListener('input', function(e) {
    if (e.target.matches('textarea')) {
        e.target.style.height = 'auto';
        e.target.style.height = e.target.scrollHeight + 'px';
    }
});

// Manejar formularios de configuración de recorrido
document.addEventListener('submit', function(e) {
    if (e.target.matches('#tour-config-form')) {
        const submitBtn = e.target.querySelector('button[type="submit"]');
        if (submitBtn) {
            const btnText = submitBtn.querySelector('.btn-text');
            const btnLoading = submitBtn.querySelector('.btn-loading');
            
            if (btnText) btnText.style.display = 'none';
            if (btnLoading) btnLoading.style.display = 'flex';
            submitBtn.disabled = true;
        }
    }
});

// Cerrar modales con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        // Cerrar cualquier modal visible
        const modals = document.querySelectorAll('.modal[style*="flex"]');
        modals.forEach(modal => {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        });
        
        // Cerrar menú si está abierto
        const menu = document.getElementById('headerMenu');
        if (menu && menu.classList.contains('active')) {
            menu.classList.remove('active');
            document.removeEventListener('click', closeMenuOnOutsideClick);
        }
    }
});

// Debug helpers
window.ProtexaDebug = {
    clearAllData() {
        localStorage.clear();
        sessionStorage.clear();
        console.log('✅ Todos los datos locales eliminados');
    },
    
    showStoredData() {
        console.log('📊 Datos almacenados:');
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key.includes('protexa')) {
                console.log(key, JSON.parse(localStorage.getItem(key)));
            }
        }
    },
    
    testAPI() {
        fetch('api/ping.php')
            .then(response => response.json())
            .then(data => console.log('🌐 API Test:', data))
            .catch(error => console.error('❌ API Error:', error));
    }
};

// Export for global access
window.ProtexaApp = ProtexaApp;
window.CameraHelper = CameraHelper;
window.StorageHelper = StorageHelper;
window.FormHelper = FormHelper;
window.NetworkHelper = NetworkHelper;