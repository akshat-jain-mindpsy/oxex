/**
 * Async Statistics Loader
 * Handles progressive loading of statistics data with Web Workers
 */

class AsyncStatsLoader {
    constructor() {
        this.worker = null;
        this.loadingStates = new Map();
        this.cache = new Map();
        this.cacheTimeout = 5 * 60 * 1000; // 5 minutes
        this.retryAttempts = 3;
        this.retryDelay = 1000;
        
        this.initWorker();
        this.setupEventListeners();
    }
    
    initWorker() {
        if (typeof Worker !== 'undefined') {
            try {
                // Construct the correct path for the worker
                const currentPath = window.location.pathname;
                const basePath = currentPath.substring(0, currentPath.lastIndexOf('/'));
                const workerPath = basePath + '/js/stats-worker.js';
                
                this.worker = new Worker(workerPath);
                this.worker.onmessage = this.handleWorkerMessage.bind(this);
                this.worker.onerror = this.handleWorkerError.bind(this);
            } catch (error) {
                console.warn('Web Worker not supported, falling back to main thread calculations:', error);
                this.worker = null;
            }
        }
    }
    
    setupEventListeners() {
        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseLoading();
            } else {
                this.resumeLoading();
            }
        });
        
        // Handle network status
        window.addEventListener('online', () => {
            this.retryFailedRequests();
        });
        
        window.addEventListener('offline', () => {
            this.pauseLoading();
        });
    }
    
    /**
     * Load data with progressive enhancement
     */
    async loadData(type, params = {}, options = {}) {
        const cacheKey = this.generateCacheKey(type, params);
        
        // Check cache first
        if (this.cache.has(cacheKey)) {
            const cached = this.cache.get(cacheKey);
            if (Date.now() - cached.timestamp < this.cacheTimeout) {
                return cached.data;
            }
            this.cache.delete(cacheKey);
        }
        
        // Set loading state
        this.setLoadingState(type, true);
        
        try {
            // Load basic data first
            const basicData = await this.loadBasicData(type, params);
            
            // Update UI with basic data immediately
            this.updateUI(type, basicData, 'basic');
            
            // Load detailed data in background
            const detailedData = await this.loadDetailedData(type, params, options);
            
            // Update UI with detailed data
            this.updateUI(type, detailedData, 'detailed');
            
            // Cache the result
            this.cache.set(cacheKey, {
                data: detailedData,
                timestamp: Date.now()
            });
            
            this.setLoadingState(type, false);
            return detailedData;
            
        } catch (error) {
            this.setLoadingState(type, false);
            this.handleError(type, error);
            throw error;
        }
    }
    
    /**
     * Load basic data quickly for immediate display
     */
    async loadBasicData(type, params) {
        const basicTypes = {
            'overview': 'overview',
            'enrollment_trend': 'enrollment_trend',
            'competency_completion': 'competency_completion',
            'recent_activity': 'recent_activity',
            'supervisor_distribution': 'supervisor_distribution'
        };
        
        const basicType = basicTypes[type] || type;
        return await this.fetchData(basicType, params);
    }
    
    /**
     * Load detailed data with heavy calculations
     */
    async loadDetailedData(type, params, options) {
        const basicData = await this.loadBasicData(type, params);
        
        // Use Web Worker for heavy calculations if available
        if (this.worker && options.useWorker !== false) {
            return await this.processWithWorker(type, basicData, options);
        } else {
            return await this.processInMainThread(type, basicData, options);
        }
    }
    
    /**
     * Process data using Web Worker
     */
    async processWithWorker(type, data, options) {
        return new Promise((resolve, reject) => {
            const requestId = Date.now().toString();
            
            const timeout = setTimeout(() => {
                reject(new Error('Worker calculation timeout'));
            }, 30000); // 30 second timeout
            
            const handleMessage = (event) => {
                if (event.data.requestId === requestId) {
                    clearTimeout(timeout);
                    this.worker.removeEventListener('message', handleMessage);
                    
                    if (event.data.status === 'success') {
                        resolve(event.data.result);
                    } else {
                        reject(new Error(event.data.error));
                    }
                }
            };
            
            this.worker.addEventListener('message', handleMessage);
            
            this.worker.postMessage({
                type: this.getWorkerCalculationType(type),
                data: data,
                options: options,
                requestId: requestId
            });
        });
    }
    
    /**
     * Process data in main thread (fallback)
     */
    async processInMainThread(type, data, options) {
        // Simulate processing time for heavy calculations
        await this.delay(100);
        
        switch (type) {
            case 'pass_fail_rates':
                return this.calculatePassFailRates(data);
            case 'competency_difficulty':
                return this.calculateCompetencyDifficulty(data);
            case 'supervisor_performance':
                return this.calculateSupervisorPerformance(data);
            case 'babcp_compliance':
                return this.calculateBABCPCompliance(data);
            default:
                return data;
        }
    }
    
    /**
     * Fetch data from server
     */
    async fetchData(type, params, attempt = 1) {
        // Use a simple relative path - this should work from any page in the oxex-admin directory
        const url = new URL('ajax/trainee_stats_data.php', window.location.href);
        url.searchParams.set('type', type);
        
        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                url.searchParams.set(key, value);
            }
        });
        
        try {
            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: AbortSignal.timeout(30000) // 30 second timeout
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const result = await response.json();
            
            if (result.status === 'error') {
                throw new Error(result.message);
            }
            
            return result.data;
            
        } catch (error) {
            if (attempt < this.retryAttempts && this.shouldRetry(error)) {
                await this.delay(this.retryDelay * attempt);
                return this.fetchData(type, params, attempt + 1);
            }
            throw error;
        }
    }
    
    /**
     * Update UI with loaded data
     */
    updateUI(type, data, level) {
        const event = new CustomEvent('statsDataLoaded', {
            detail: { type, data, level }
        });
        document.dispatchEvent(event);
    }
    
    /**
     * Set loading state for a data type
     */
    setLoadingState(type, isLoading) {
        this.loadingStates.set(type, isLoading);
        
        const event = new CustomEvent('statsLoadingStateChanged', {
            detail: { type, isLoading }
        });
        document.dispatchEvent(event);
    }
    
    /**
     * Handle worker messages
     */
    handleWorkerMessage(event) {
        const { status, type, result, error, requestId } = event.data;
        
        if (status === 'success') {
            this.updateUI(type, result, 'detailed');
        } else {
            console.error('Worker error:', error);
            this.handleError(type, new Error(error));
        }
    }
    
    /**
     * Handle worker errors
     */
    handleWorkerError(error) {
        console.error('Web Worker error:', error);
        // Fall back to main thread processing
        this.worker = null;
    }
    
    /**
     * Handle general errors
     */
    handleError(type, error) {
        console.error(`Error loading ${type}:`, error);
        
        const event = new CustomEvent('statsError', {
            detail: { type, error: error.message }
        });
        document.dispatchEvent(event);
    }
    
    /**
     * Generate cache key
     */
    generateCacheKey(type, params) {
        return `${type}_${JSON.stringify(params)}`;
    }
    
    /**
     * Get worker calculation type
     */
    getWorkerCalculationType(type) {
        const mapping = {
            'pass_fail_rates': 'calculate_pass_fail_rates',
            'competency_difficulty': 'calculate_competency_difficulty',
            'supervisor_performance': 'calculate_supervisor_performance',
            'babcp_compliance': 'calculate_babcp_compliance',
            'monthly_trends': 'calculate_monthly_trends'
        };
        
        return mapping[type] || type;
    }
    
    /**
     * Check if error should trigger retry
     */
    shouldRetry(error) {
        return error.name === 'TypeError' || // Network error
               error.message.includes('timeout') ||
               error.message.includes('HTTP 5');
    }
    
    /**
     * Pause loading operations
     */
    pauseLoading() {
        // Cancel ongoing requests
        this.loadingStates.forEach((isLoading, type) => {
            if (isLoading) {
                this.setLoadingState(type, false);
            }
        });
    }
    
    /**
     * Resume loading operations
     */
    resumeLoading() {
        // Could implement queue of pending requests
        console.log('Resuming loading operations');
    }
    
    /**
     * Retry failed requests
     */
    retryFailedRequests() {
        // Could implement retry queue
        console.log('Retrying failed requests');
    }
    
    /**
     * Clear cache
     */
    clearCache() {
        this.cache.clear();
    }
    
    /**
     * Utility delay function
     */
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
    
    // Fallback calculation methods for main thread
    calculatePassFailRates(data) {
        return data.map(competency => ({
            ...competency,
            passRate: competency.total_trainees > 0 ? 
                Math.round((competency.passed / competency.total_trainees) * 1000) / 10 : 0,
            failRate: competency.total_trainees > 0 ? 
                Math.round((competency.failed / competency.total_trainees) * 1000) / 10 : 0
        }));
    }
    
    calculateCompetencyDifficulty(data) {
        return data.map(competency => ({
            ...competency,
            successRate: Math.round((competency.successful_attempts / competency.total_attempts) * 1000) / 10,
            difficulty: competency.successful_attempts / competency.total_attempts >= 0.8 ? 'easy' : 
                       competency.successful_attempts / competency.total_attempts < 0.6 ? 'hard' : 'medium'
        }));
    }
    
    calculateSupervisorPerformance(data) {
        return data.map(supervisor => ({
            ...supervisor,
            performanceScore: Math.round((supervisor.avg_completion_rate / 100) * 5 * 10) / 10,
            rating: supervisor.avg_completion_rate >= 90 ? 'excellent' :
                   supervisor.avg_completion_rate >= 70 ? 'good' :
                   supervisor.avg_completion_rate >= 50 ? 'satisfactory' : 'needs_improvement'
        }));
    }
    
    calculateBABCPCompliance(data) {
        return data.map(trainee => {
            let complianceScore = 0;
            if (trainee.babcp_training_cases > 0) complianceScore += 25;
            if (trainee.supervised_cases >= 3) complianceScore += 25;
            if (trainee.cbt_cases > 0) complianceScore += 25;
            if (trainee.cases_with_5plus_sessions > 0) complianceScore += 25;
            
            return {
                ...trainee,
                complianceScore,
                status: complianceScore >= 100 ? 'fully_compliant' :
                       complianceScore >= 75 ? 'mostly_compliant' :
                       complianceScore >= 50 ? 'partially_compliant' : 'non_compliant'
            };
        });
    }
    
    /**
     * Cleanup resources
     */
    destroy() {
        if (this.worker) {
            this.worker.terminate();
            this.worker = null;
        }
        this.cache.clear();
        this.loadingStates.clear();
    }
}

// Export for use in other modules
window.AsyncStatsLoader = AsyncStatsLoader;
