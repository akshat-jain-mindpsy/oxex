/**
 * Web Worker for heavy statistical calculations
 * Handles complex data processing without blocking the main thread
 */

// Import Chart.js for calculations (if needed)
// Note: Web Workers have limited access to DOM and some libraries

self.onmessage = function(e) {
    const { type, data, options } = e.data;
    
    try {
        let result;
        
        switch (type) {
            case 'calculate_pass_fail_rates':
                result = calculatePassFailRates(data);
                break;
                
            case 'calculate_competency_difficulty':
                result = calculateCompetencyDifficulty(data);
                break;
                
            case 'calculate_supervisor_performance':
                result = calculateSupervisorPerformance(data);
                break;
                
            case 'calculate_babcp_compliance':
                result = calculateBABCPCompliance(data);
                break;
                
            case 'calculate_monthly_trends':
                result = calculateMonthlyTrends(data);
                break;
                
            case 'aggregate_large_dataset':
                result = aggregateLargeDataset(data, options);
                break;
                
            default:
                throw new Error(`Unknown calculation type: ${type}`);
        }
        
        self.postMessage({
            status: 'success',
            type: type,
            result: result,
            timestamp: new Date().toISOString()
        });
        
    } catch (error) {
        self.postMessage({
            status: 'error',
            type: type,
            error: error.message,
            timestamp: new Date().toISOString()
        });
    }
};

/**
 * Calculate pass/fail rates with statistical analysis
 */
function calculatePassFailRates(data) {
    const results = data.map(competency => {
        const total = competency.passed + competency.failed;
        const passRate = total > 0 ? (competency.passed / total) * 100 : 0;
        const failRate = total > 0 ? (competency.failed / total) * 100 : 0;
        
        // Calculate confidence interval for pass rate
        const confidenceInterval = calculateConfidenceInterval(
            competency.passed, 
            total, 
            0.95
        );
        
        // Determine difficulty level based on pass rate
        let difficulty = 'medium';
        if (passRate >= 80) difficulty = 'easy';
        else if (passRate < 60) difficulty = 'hard';
        
        return {
            ...competency,
            passRate: Math.round(passRate * 10) / 10,
            failRate: Math.round(failRate * 10) / 10,
            confidenceInterval: confidenceInterval,
            difficulty: difficulty,
            statisticalSignificance: total >= 30 ? 'significant' : 'insufficient_data'
        };
    });
    
    return {
        competencies: results,
        summary: {
            totalCompetencies: results.length,
            averagePassRate: results.reduce((sum, c) => sum + c.passRate, 0) / results.length,
            easyCompetencies: results.filter(c => c.difficulty === 'easy').length,
            hardCompetencies: results.filter(c => c.difficulty === 'hard').length
        }
    };
}

/**
 * Calculate competency difficulty with advanced metrics
 */
function calculateCompetencyDifficulty(data) {
    const results = data.map(competency => {
        const successRate = competency.successful_attempts / competency.total_attempts * 100;
        
        // Calculate standard deviation for difficulty assessment
        const variance = calculateVariance(competency.attempts || []);
        const standardDeviation = Math.sqrt(variance);
        
        // Determine difficulty based on success rate and consistency
        let difficulty = 'medium';
        let difficultyScore = 0;
        
        if (successRate >= 80) {
            difficulty = 'easy';
            difficultyScore = 1;
        } else if (successRate < 60) {
            difficulty = 'hard';
            difficultyScore = 3;
        } else {
            difficultyScore = 2;
        }
        
        // Adjust for consistency (lower standard deviation = more consistent = easier)
        if (standardDeviation < 10) difficultyScore -= 0.5;
        else if (standardDeviation > 20) difficultyScore += 0.5;
        
        return {
            ...competency,
            successRate: Math.round(successRate * 10) / 10,
            difficulty: difficulty,
            difficultyScore: Math.max(1, Math.min(5, difficultyScore)),
            standardDeviation: Math.round(standardDeviation * 10) / 10,
            consistency: standardDeviation < 10 ? 'high' : standardDeviation < 20 ? 'medium' : 'low'
        };
    });
    
    return {
        competencies: results.sort((a, b) => a.difficultyScore - b.difficultyScore),
        summary: {
            averageDifficulty: results.reduce((sum, c) => sum + c.difficultyScore, 0) / results.length,
            mostDifficult: results[results.length - 1],
            easiest: results[0]
        }
    };
}

/**
 * Calculate supervisor performance metrics
 */
function calculateSupervisorPerformance(data) {
    const results = data.map(supervisor => {
        const performanceScore = calculatePerformanceScore(supervisor);
        const trend = calculateTrend(supervisor.historicalData || []);
        
        return {
            ...supervisor,
            performanceScore: Math.round(performanceScore * 10) / 10,
            trend: trend,
            rating: getPerformanceRating(performanceScore),
            recommendations: generateRecommendations(supervisor, performanceScore)
        };
    });
    
    return {
        supervisors: results.sort((a, b) => b.performanceScore - a.performanceScore),
        summary: {
            topPerformer: results[0],
            averagePerformance: results.reduce((sum, s) => sum + s.performanceScore, 0) / results.length,
            improvementNeeded: results.filter(s => s.performanceScore < 3).length
        }
    };
}

/**
 * Calculate BABCP compliance metrics
 */
function calculateBABCPCompliance(data) {
    const results = data.map(trainee => {
        const complianceScore = calculateComplianceScore(trainee);
        const requirements = {
            babcpTraining: trainee.babcp_training_cases > 0,
            supervisedCases: trainee.supervised_cases >= 3,
            cbtCases: trainee.cbt_cases > 0,
            longTermCases: trainee.cases_with_5plus_sessions > 0
        };
        
        const metRequirements = Object.values(requirements).filter(Boolean).length;
        const compliancePercentage = (metRequirements / 4) * 100;
        
        return {
            ...trainee,
            complianceScore: complianceScore,
            compliancePercentage: Math.round(compliancePercentage),
            requirements: requirements,
            metRequirements: metRequirements,
            status: getComplianceStatus(compliancePercentage),
            nextSteps: generateNextSteps(requirements)
        };
    });
    
    return {
        trainees: results,
        summary: {
            totalTrainees: results.length,
            fullyCompliant: results.filter(t => t.compliancePercentage === 100).length,
            partiallyCompliant: results.filter(t => t.compliancePercentage >= 50 && t.compliancePercentage < 100).length,
            nonCompliant: results.filter(t => t.compliancePercentage < 50).length,
            averageCompliance: results.reduce((sum, t) => sum + t.compliancePercentage, 0) / results.length
        }
    };
}

/**
 * Calculate monthly trends with seasonality analysis
 */
function calculateMonthlyTrends(data) {
    const monthlyData = data.reduce((acc, entry) => {
        const month = entry.month;
        if (!acc[month]) {
            acc[month] = { entries: 0, activeTrainees: 0, count: 0 };
        }
        acc[month].entries += entry.entries;
        acc[month].activeTrainees += entry.active_trainees;
        acc[month].count += 1;
        return acc;
    }, {});
    
    const trends = Object.entries(monthlyData).map(([month, data]) => ({
        month,
        entries: data.entries,
        activeTrainees: data.activeTrainees,
        averageEntriesPerTrainee: data.entries / data.activeTrainees || 0
    })).sort((a, b) => a.month.localeCompare(b.month));
    
    // Calculate trend direction
    const trendDirection = calculateTrendDirection(trends.map(t => t.entries));
    
    return {
        trends,
        summary: {
            trendDirection,
            peakMonth: trends.reduce((max, t) => t.entries > max.entries ? t : max, trends[0]),
            lowMonth: trends.reduce((min, t) => t.entries < min.entries ? t : min, trends[0]),
            averageMonthlyEntries: trends.reduce((sum, t) => sum + t.entries, 0) / trends.length
        }
    };
}

/**
 * Aggregate large datasets efficiently
 */
function aggregateLargeDataset(data, options = {}) {
    const { groupBy, aggregateFields, filters = {} } = options;
    
    const grouped = data.reduce((acc, item) => {
        const key = groupBy ? item[groupBy] : 'all';
        if (!acc[key]) {
            acc[key] = [];
        }
        acc[key].push(item);
        return acc;
    }, {});
    
    const aggregated = Object.entries(grouped).map(([key, items]) => {
        const result = { [groupBy || 'group']: key };
        
        aggregateFields.forEach(field => {
            if (field.type === 'sum') {
                result[field.name] = items.reduce((sum, item) => sum + (item[field.source] || 0), 0);
            } else if (field.type === 'avg') {
                result[field.name] = items.reduce((sum, item) => sum + (item[field.source] || 0), 0) / items.length;
            } else if (field.type === 'count') {
                result[field.name] = items.length;
            } else if (field.type === 'max') {
                result[field.name] = Math.max(...items.map(item => item[field.source] || 0));
            } else if (field.type === 'min') {
                result[field.name] = Math.min(...items.map(item => item[field.source] || 0));
            }
        });
        
        return result;
    });
    
    return {
        data: aggregated,
        summary: {
            totalGroups: aggregated.length,
            totalItems: data.length,
            averageItemsPerGroup: data.length / aggregated.length
        }
    };
}

// Helper functions
function calculateConfidenceInterval(successes, total, confidence) {
    if (total === 0) return [0, 0];
    
    const p = successes / total;
    const z = confidence === 0.95 ? 1.96 : 2.576; // 95% or 99%
    const margin = z * Math.sqrt((p * (1 - p)) / total);
    
    return [
        Math.max(0, (p - margin) * 100),
        Math.min(100, (p + margin) * 100)
    ];
}

function calculateVariance(attempts) {
    if (attempts.length === 0) return 0;
    
    const mean = attempts.reduce((sum, val) => sum + val, 0) / attempts.length;
    const squaredDiffs = attempts.map(val => Math.pow(val - mean, 2));
    return squaredDiffs.reduce((sum, val) => sum + val, 0) / attempts.length;
}

function calculatePerformanceScore(supervisor) {
    const weights = {
        completionRate: 0.4,
        traineeCount: 0.2,
        avgEntries: 0.2,
        consistency: 0.2
    };
    
    let score = 0;
    score += (supervisor.avg_completion_rate / 100) * weights.completionRate * 5;
    score += Math.min(supervisor.trainee_count / 10, 1) * weights.traineeCount * 5;
    score += Math.min(supervisor.avg_entries / 50, 1) * weights.avgEntries * 5;
    score += 3 * weights.consistency; // Default consistency score
    
    return score;
}

function calculateTrend(historicalData) {
    if (historicalData.length < 2) return 'stable';
    
    const recent = historicalData.slice(-3);
    const older = historicalData.slice(-6, -3);
    
    const recentAvg = recent.reduce((sum, val) => sum + val, 0) / recent.length;
    const olderAvg = older.reduce((sum, val) => sum + val, 0) / older.length;
    
    const change = ((recentAvg - olderAvg) / olderAvg) * 100;
    
    if (change > 10) return 'improving';
    if (change < -10) return 'declining';
    return 'stable';
}

function getPerformanceRating(score) {
    if (score >= 4.5) return 'excellent';
    if (score >= 3.5) return 'good';
    if (score >= 2.5) return 'satisfactory';
    if (score >= 1.5) return 'needs_improvement';
    return 'poor';
}

function generateRecommendations(supervisor, score) {
    const recommendations = [];
    
    if (supervisor.avg_completion_rate < 70) {
        recommendations.push('Focus on improving trainee completion rates');
    }
    if (supervisor.trainee_count > 15) {
        recommendations.push('Consider reducing trainee load for better supervision quality');
    }
    if (supervisor.avg_entries < 20) {
        recommendations.push('Encourage more detailed logging from trainees');
    }
    
    return recommendations;
}

function calculateComplianceScore(trainee) {
    let score = 0;
    if (trainee.babcp_training_cases > 0) score += 25;
    if (trainee.supervised_cases >= 3) score += 25;
    if (trainee.cbt_cases > 0) score += 25;
    if (trainee.cases_with_5plus_sessions > 0) score += 25;
    return score;
}

function getComplianceStatus(percentage) {
    if (percentage === 100) return 'fully_compliant';
    if (percentage >= 75) return 'mostly_compliant';
    if (percentage >= 50) return 'partially_compliant';
    return 'non_compliant';
}

function generateNextSteps(requirements) {
    const nextSteps = [];
    
    if (!requirements.babcpTraining) {
        nextSteps.push('Complete BABCP training cases');
    }
    if (!requirements.supervisedCases) {
        nextSteps.push('Complete at least 3 supervised cases');
    }
    if (!requirements.cbtCases) {
        nextSteps.push('Complete CBT case work');
    }
    if (!requirements.longTermCases) {
        nextSteps.push('Complete cases with 5+ sessions');
    }
    
    return nextSteps;
}

function calculateTrendDirection(values) {
    if (values.length < 2) return 'stable';
    
    const firstHalf = values.slice(0, Math.floor(values.length / 2));
    const secondHalf = values.slice(Math.floor(values.length / 2));
    
    const firstAvg = firstHalf.reduce((sum, val) => sum + val, 0) / firstHalf.length;
    const secondAvg = secondHalf.reduce((sum, val) => sum + val, 0) / secondHalf.length;
    
    const change = ((secondAvg - firstAvg) / firstAvg) * 100;
    
    if (change > 5) return 'increasing';
    if (change < -5) return 'decreasing';
    return 'stable';
}
