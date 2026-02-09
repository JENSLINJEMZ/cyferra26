// Updated loadAnalyticsData function
async function loadAnalyticsData() {
    try {
        const period = document.getElementById('timeRange').value;
        
        // Load all data in parallel
        const [
            kpiData,
            registrationTrends,
            paymentStatus,
            eventPopularity,
            collegeDistribution,
            adminActivity,
            demographics,
            revenueData,
            heatmapData
        ] = await Promise.all([
            fetchKPIStats(period),
            fetchRegistrationTrends('daily', period),
            fetchPaymentStatus(period),
            fetchEventPopularity(period),
            fetchCollegeDistribution(10, period),
            fetchAdminActivity(period),
            fetchDemographics(period),
            fetchRevenueData('daily'),
            fetchHeatmapData()
        ]);
        
        // Update KPI counters
        if (kpiData.success) {
            animateCounter('totalRegistrations', kpiData.data.total_registrations);
            animateCounter('verifiedPayments', kpiData.data.verified_payments);
            animateCounter('pendingVerifications', kpiData.data.pending_verifications);
            animateCounter('totalRevenue', kpiData.data.total_revenue);
            animateCounter('adminActivities', kpiData.data.admin_activities);
            animateCounter('avgResponse', kpiData.data.avg_response_time);
        }
        
        // Update charts with real data
        if (registrationTrends.success) {
            updateRegistrationTrendsChart(registrationTrends.data);
        }
        
        if (paymentStatus.success) {
            updatePaymentStatusChart(paymentStatus.data);
        }
        
        if (eventPopularity.success) {
            updateEventPopularityChart(eventPopularity.data);
        }
        
        if (collegeDistribution.success) {
            updateCollegeDistributionChart(collegeDistribution.data);
        }
        
        if (adminActivity.success) {
            updateAdminActivityChart(adminActivity.data);
        }
        
        if (demographics.success) {
            updateDemographicsCharts(demographics.data);
        }
        
        if (revenueData.success) {
            updateRevenueChart(revenueData.data);
        }
        
        if (heatmapData.success) {
            updateHeatmap(heatmapData.data);
        }
        
        // Load admin performance
        await loadAdminPerformance();
        
    } catch (error) {
        console.error('Error loading analytics:', error);
        showToast('Failed to load analytics data', 'error');
    }
}

// API Fetch Functions
async function fetchKPIStats(period) {
    const response = await fetch(`api/analytics/kpi.php?period=${period}`);
    return await response.json();
}

async function fetchRegistrationTrends(type, period) {
    const response = await fetch(`api/analytics/registration-trends.php?type=${type}&period=${period}`);
    return await response.json();
}

async function fetchPaymentStatus(period) {
    const response = await fetch(`api/analytics/payment-status.php?period=${period}`);
    return await response.json();
}

async function fetchEventPopularity(period) {
    const response = await fetch(`api/analytics/event-popularity.php?period=${period}`);
    return await response.json();
}

async function fetchCollegeDistribution(limit = 10, period = 'all') {
    const response = await fetch(`api/analytics/college-distribution.php?limit=${limit}&period=${period}`);
    return await response.json();
}

async function fetchAdminActivity(period) {
    const response = await fetch(`api/analytics/admin-activity.php?period=${period}`);
    return await response.json();
}

async function fetchDemographics(period) {
    const response = await fetch(`api/analytics/demographics.php?period=${period}`);
    return await response.json();
}

async function fetchRevenueData(type) {
    const response = await fetch(`api/analytics/revenue.php?type=${type}`);
    return await response.json();
}

async function fetchHeatmapData() {
    const response = await fetch('api/analytics/heatmap.php');
    return await response.json();
}

// Chart Update Functions
function updateRegistrationTrendsChart(data) {
    if (charts.registrationTrends) {
        charts.registrationTrends.destroy();
    }
    
    const container = document.getElementById('registrationTrendsChart');
    const loading = document.getElementById('trendsLoading');
    loading.style.display = 'none';
    
    const ctx = document.createElement('canvas');
    container.appendChild(ctx);
    
    charts.registrationTrends = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Registrations',
                    data: data.totals,
                    borderColor: '#00d4ff',
                    backgroundColor: 'rgba(0, 212, 255, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Verified',
                    data: data.verified,
                    borderColor: '#00ff88',
                    backgroundColor: 'rgba(0, 255, 136, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: getChartOptions('Registration Trends')
    });
}

function updatePaymentStatusChart(data) {
    if (charts.paymentStatus) {
        charts.paymentStatus.destroy();
    }
    
    const container = document.getElementById('paymentStatusChart');
    const loading = document.getElementById('paymentLoading');
    loading.style.display = 'none';
    
    const ctx = document.createElement('canvas');
    container.appendChild(ctx);
    
    charts.paymentStatus = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.data,
                backgroundColor: data.colors,
                borderWidth: 0,
                hoverOffset: 15
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            animation: {
                animateRotate: true,
                animateScale: true,
                duration: 1500
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const percentage = data.percentages[context.dataIndex] || 0;
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

function updateEventPopularityChart(data) {
    if (charts.eventPopularity) {
        charts.eventPopularity.destroy();
    }
    
    const container = document.getElementById('eventPopularityChart');
    const loading = document.getElementById('eventsLoading');
    loading.style.display = 'none';
    
    const ctx = document.createElement('canvas');
    container.appendChild(ctx);
    
    charts.eventPopularity = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.events,
            datasets: [{
                label: 'Registrations',
                data: data.registrations,
                backgroundColor: data.colors,
                borderWidth: 0,
                borderRadius: 8,
                borderSkipped: false,
            }]
        },
        options: getChartOptions('Event Popularity', true)
    });
}

// Helper function for chart options
function getChartOptions(title, isBar = false) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
            duration: 1500,
            easing: 'easeOutQuart'
        },
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    color: '#a0a0c0',
                    font: { family: 'Poppins' }
                }
            }
        },
        scales: {
            x: {
                grid: {
                    color: 'rgba(255, 255, 255, 0.05)',
                    display: !isBar
                },
                ticks: {
                    color: '#a0a0c0',
                    maxRotation: isBar ? 45 : 0
                }
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(255, 255, 255, 0.05)'
                },
                ticks: {
                    color: '#a0a0c0',
                    callback: function(value) {
                        return value.toLocaleString();
                    }
                }
            }
        }
    };
}