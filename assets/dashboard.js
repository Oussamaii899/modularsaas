/**
 * Dashboard JavaScript
 * Handles Chart.js initialization, Daterangepicker, and live data fetching.
 */

document.addEventListener("DOMContentLoaded", function() {
    const dashboardContainer = document.getElementById('dashboard-container');
    if (!dashboardContainer) return;

    const dataUrl = dashboardContainer.getAttribute('data-data-url');
    const salesLabel = dashboardContainer.getAttribute('data-sales-label') || 'Sales';
    const purchasesLabel = dashboardContainer.getAttribute('data-purchases-label') || 'Purchases';

    // Re-initialize lucide icons for any dynamically added DOM
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Daterangepicker initialization (jQuery required)
    if (typeof jQuery !== 'undefined' && typeof moment !== 'undefined') {
        var start = moment().subtract(29, 'days');
        var end = moment();

        function updateDateRangeDisplay(start, end, label) {
            $('#reportrange span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
            
            const params = new URLSearchParams({
                date: start.format('YYYY-MM-DD'),
                end: end.format('YYYY-MM-DD')
            });
            
            if (label) {
                params.append('label', label);
            }
            
            const url = `${dataUrl}?${params.toString()}`;
            
            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                console.log(data)
                
                if (document.getElementById('total-sales-value')) {
                    const totalSalesValue = data.total_sales ?? 0;
                    document.getElementById('total-sales-value').innerText = '$' + Number(totalSalesValue).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }

                if (document.getElementById('total-purchases-value')) {
                    const totalPurchaseValue = data.total_purchases ?? 0;
                    document.getElementById('total-purchases-value').innerText = '$' + Number(totalPurchaseValue).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }

                if (document.getElementById('net-profit-value')) {
                    const totalSalesValue = data.total_sales ?? 0;
                    const totalPurchaseValue = data.total_purchases ?? 0;
                    const netSales = totalSalesValue - Math.abs(data.total_refunded_sales ?? 0);
                    const netPurchases = totalPurchaseValue - Math.abs(data.total_refunded_purchases ?? 0);
                    const netProfit = netSales - netPurchases;
                    if(netProfit > 0){
                        document.getElementById('net-profit-value').innerText = '$' + Number(netProfit).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    } else {
                        document.getElementById('net-profit-value').innerText = '$0.00';
                    }
                }

                if (document.getElementById('net-loss-value')) {
                    const totalSalesValue = data.total_sales ?? 0;
                    const totalPurchaseValue = data.total_purchases ?? 0;
                    const netSales = totalSalesValue - Math.abs(data.total_refunded_sales ?? 0);
                    const netPurchases = totalPurchaseValue - Math.abs(data.total_refunded_purchases ?? 0);
                    const netLoss = netPurchases - netSales;
                    if(netLoss > 0){
                        document.getElementById('net-loss-value').innerText = '$' + Number(netLoss).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    } else {
                        document.getElementById('net-loss-value').innerText = '$0.00';
                    }
                }

                if (document.getElementById('customer-refunds-value')) {
                    document.getElementById('customer-refunds-value').innerText = '$' + Number(data.total_refunded_sales ?? 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }
                if (document.getElementById('supplier-refunds-value')) {
                    document.getElementById('supplier-refunds-value').innerText = '$' + Number(data.total_refunded_purchases ?? 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }
                if (document.getElementById('sales-receivables-value')) {
                    document.getElementById('sales-receivables-value').innerText = '$' + Number(data.total_outstanding_sales ?? 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }
                if (document.getElementById('purchases-payables-value')) {
                    document.getElementById('purchases-payables-value').innerText = '$' + Number(data.total_outstanding_purchases ?? 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }

                // Update doctor mode widgets
                if (data.doctor_stats) {
                    if (document.getElementById('total-patients-value')) {
                        document.getElementById('total-patients-value').innerText = data.doctor_stats.total_patients ?? 0;
                    }
                    if (document.getElementById('consultations-count-value')) {
                        document.getElementById('consultations-count-value').innerText = data.doctor_stats.consultations_count ?? 0;
                    }
                    if (document.getElementById('pending-payments-value')) {
                        document.getElementById('pending-payments-value').innerText = data.doctor_stats.pending_payments_count ?? 0;
                    }
                    if (document.getElementById('income-this-month-value')) {
                        const incomeVal = data.doctor_stats.income_this_month ?? 0;
                        document.getElementById('income-this-month-value').innerText = '$' + Number(incomeVal).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    }
                }

                const timeframeText = label || (start.format('MMM D') + ' - ' + end.format('MMM D'));
                if (document.getElementById('total-sales-change-text')) {
                    document.getElementById('total-sales-change-text').innerText = timeframeText;
                }
                if (document.getElementById('total-purchases-change-text')) {
                    document.getElementById('total-purchases-change-text').innerText = timeframeText;
                }
                if (document.getElementById('net-profit-change-text')) {
                    document.getElementById('net-profit-change-text').innerText = timeframeText;
                }
                if (document.getElementById('net-loss-change-text')) {
                    document.getElementById('net-loss-change-text').innerText = timeframeText;
                }

                if (document.getElementById('customer-refunds-change-text')) {
                    document.getElementById('customer-refunds-change-text').innerText = timeframeText;
                }
                if (document.getElementById('supplier-refunds-change-text')) {
                    document.getElementById('supplier-refunds-change-text').innerText = timeframeText;
                }
                if (document.getElementById('sales-receivables-change-text')) {
                    document.getElementById('sales-receivables-change-text').innerText = timeframeText;
                }
                if (document.getElementById('purchases-payables-change-text')) {
                    document.getElementById('purchases-payables-change-text').innerText = timeframeText;
                }
                
                // Update Main Chart
                if (window.mainChartInstance) {
                    const dailyTotals = {};
                    const totalDays = moment(end).diff(moment(start), 'days');
                    const groupMode = totalDays > 60 ? 'month' : 'day';
                    const keyFormat = groupMode === 'month' ? 'YYYY-MM' : 'YYYY-MM-DD';
                    const labelFormat = groupMode === 'month' ? 'MMM YYYY' : 'MMM D';
                    
                    let currDate = moment(start).startOf(groupMode);
                    const endDate = moment(end).startOf(groupMode);
                    while (currDate.diff(endDate) <= 0) {
                        const dateKey = currDate.format(keyFormat);
                        dailyTotals[dateKey] = { sales: 0, purchases: 0, label: currDate.format(labelFormat) };
                        currDate.add(1, groupMode + 's');
                        if (Object.keys(dailyTotals).length > 365) break;
                    }

                    function processItems(items, type) {
                        if (!Array.isArray(items)) return;
                        items.forEach(item => {
                            const dateVal = typeof item.created_at === 'object' && item.created_at !== null ? item.created_at.date : item.created_at;
                            const dateKey = moment(dateVal).format(keyFormat);
                            if (!dailyTotals[dateKey]) {
                                dailyTotals[dateKey] = { sales: 0, purchases: 0, label: moment(dateVal).format(labelFormat) };
                            }
                            dailyTotals[dateKey][type] += parseFloat(item.total || 0);
                        });
                    }

                    processItems(data.sales, 'sales');
                    processItems(data.purchases, 'purchases');

                    const sortedDateKeys = Object.keys(dailyTotals).sort();
                    const labels = sortedDateKeys.map(k => dailyTotals[k].label);
                    const salesData = sortedDateKeys.map(k => dailyTotals[k].sales);
                    const purchasesData = sortedDateKeys.map(k => dailyTotals[k].purchases);

                    window.mainChartInstance.data.labels = labels;
                    window.mainChartInstance.data.datasets[0].data = salesData;
                    window.mainChartInstance.data.datasets[1].data = purchasesData;
                    window.mainChartInstance.update();
                }
                
                // Update Pie Chart
                if (window.pieChartInstance && data.salesByProduct) {
                    const products = data.salesByProduct;
                    let labels = [];
                    let chartData = [];
                    
                    if (products.length <= 6) {
                        labels = products.map(p => p.name || 'Unknown');
                        chartData = products.map(p => parseFloat(p.revenue));
                    } else {
                        const top5 = products.slice(0, 5);
                        const others = products.slice(5);
                        labels = top5.map(p => p.name || 'Unknown');
                        chartData = top5.map(p => parseFloat(p.revenue));
                        const othersTotal = others.reduce((sum, p) => sum + parseFloat(p.revenue), 0);
                        labels.push('Other');
                        chartData.push(othersTotal);
                    }
                    
                    window.pieChartInstance.data.labels = labels;
                    window.pieChartInstance.data.datasets[0].data = chartData;
                    window.pieChartInstance.update();
                }

                // Update Stock Alerts
                const stockAlertsList = document.getElementById('stock-alerts-list');
                if (stockAlertsList && data.lowStock) {
                    stockAlertsList.innerHTML = '';
                    if (data.lowStock.length === 0) {
                        stockAlertsList.innerHTML = '<div class="text-center py-8 text-slate-400 text-sm">No stock alerts found</div>';
                    } else {
                        data.lowStock.forEach(product => {
                            const isCritical = product.stockQuantity <= 5;
                            const bgColor = isCritical ? '#fef2f2' : '#fffbeb';
                            const textColor = isCritical ? '#dc2626' : '#d97706';
                            const borderColor = isCritical ? '#fee2e2' : '#fef3c7';
                            const dotColor = isCritical ? '#ef4444' : '#f59e0b';
                            const detailUrl = `/products/${product.slug || product.id}`;

                            const itemHtml = `
                                <a href="${detailUrl}" class="flex items-center gap-4 p-3 rounded-xl hover:bg-slate-50/80 transition-colors border border-transparent hover:border-slate-100 group block">
                                    <div class="flex items-center gap-4 w-full">
                                        <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 flex-shrink-0 shadow-sm border border-indigo-100">
                                            <i data-lucide="package" class="w-5 h-5"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-bold text-slate-900 truncate group-hover:text-indigo-600 transition-colors">${product.name || 'Unknown Product'}</p>
                                            <p class="text-xs text-slate-500 mt-0.5 truncate">$${parseFloat(product.price || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})} &bull; General</p>
                                        </div>
                                        <div class="text-right flex-shrink-0" style="display: flex; align-items: center; justify-content: flex-end;">
                                            <span style="font-size: 10px; font-weight: 800; text-transform: uppercase; padding: 4px 8px; border-radius: 6px; display: inline-flex; align-items: center; border: 1px solid ${borderColor}; background-color: ${bgColor}; color: ${textColor}; min-width: 60px; justify-content: center; box-sizing: border-box; line-height: 1;">
                                                <span style="width: 6px; height: 6px; border-radius: 9999px; background-color: ${dotColor}; margin-right: 6px; display: inline-block; flex-shrink: 0;"></span>
                                                ${product.stockQuantity || 0} left
                                            </span>
                                        </div>
                                    </div>
                                </a>`;
                            stockAlertsList.insertAdjacentHTML('beforeend', itemHtml);
                        });
                    }
                }

                // Update Recent Transactions
                const transactionsList = document.getElementById('recent-transactions-list');
                if (transactionsList && data.recentTransactions) {
                    transactionsList.innerHTML = '';
                    if (data.recentTransactions.length === 0) {
                        transactionsList.innerHTML = '<div class="text-center py-8 text-slate-400 text-sm">No transactions found</div>';
                    } else {
                        data.recentTransactions.forEach(tx => {
                            const isSale = tx.type === 'sale';
                            const icon = isSale ? 'arrow-down-left' : 'arrow-up-right';
                            const bgColor = isSale ? 'bg-emerald-50' : 'bg-rose-50';
                            const textColor = isSale ? 'text-emerald-600' : 'text-rose-600';
                            const borderClass = isSale ? 'border-emerald-100' : 'border-rose-100';
                            const prefix = isSale ? '+' : '-';
                            const label = isSale ? 'Sale' : 'Purchase';
                            const contactName = tx.contact ? tx.contact.name : 'Unknown Contact';
                            const rawDate = tx.created_at && tx.created_at.date ? tx.created_at.date : tx.created_at;
                            const dateStr = moment(rawDate).fromNow();
                            const ref = isSale ? `INV-${tx.id}` : `PUR-${tx.id}`;

                            const status = tx.paymentStatus || 'Paid';
                            let statusBadgeHtml = '';
                            if (status === 'Paid') {
                                statusBadgeHtml = `<span style="font-size: 9px; font-weight: 800; background-color: #ecfdf5; color: #059669; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; line-height: 1;">Paid</span>`;
                            } else if (status === 'Partial') {
                                statusBadgeHtml = `<span style="font-size: 9px; font-weight: 800; background-color: #fffbeb; color: #d97706; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; line-height: 1;">Partial</span>`;
                            } else if (status === 'Unpaid') {
                                statusBadgeHtml = `<span style="font-size: 9px; font-weight: 800; background-color: #fef2f2; color: #dc2626; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; line-height: 1;">Unpaid</span>`;
                            } else if (status === 'Overpaid') {
                                statusBadgeHtml = `<span style="font-size: 9px; font-weight: 800; background-color: #fff1f2; color: #e11d48; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; line-height: 1;">Overpaid</span>`;
                            } else if (status === 'Refunded') {
                                statusBadgeHtml = `<span style="font-size: 9px; font-weight: 800; background-color: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; line-height: 1;">Refunded</span>`;
                            } else if (status === 'Cancelled') {
                                statusBadgeHtml = `<span style="font-size: 9px; font-weight: 800; background-color: #fef2f2; color: #dc2626; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; line-height: 1;">Cancelled</span>`;
                            }

                            const isCancelled = status === 'Cancelled';
                            const isRefunded = status === 'Refunded';
                            
                            let priceColor = textColor;
                            let priceDecoration = '';
                            if (isCancelled) {
                                priceColor = 'text-slate-400';
                                priceDecoration = 'line-through';
                            } else if (isRefunded) {
                                priceColor = 'text-slate-500';
                            }

                            const detailUrl = isSale ? `/sales/${tx.slug}` : `/purchases/${tx.slug}`;

                            const itemHtml = `
                                <a href="${detailUrl}" class="flex items-center gap-4 p-3 rounded-xl hover:bg-slate-50/80 transition-colors border border-transparent hover:border-slate-100 group block">
                                    <div class="flex items-center gap-4 w-full">
                                        <div class="w-12 h-12 rounded-xl ${bgColor} flex items-center justify-center ${textColor} flex-shrink-0 shadow-sm border ${borderClass}">
                                            <i data-lucide="${icon}" class="w-5 h-5"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-bold text-slate-900 truncate group-hover:text-indigo-600 transition-colors">${label}: ${contactName}</p>
                                            <p class="text-xs text-slate-500 mt-0.5">${dateStr} &bull; ${ref}</p>
                                        </div>
                                        <div class="text-right flex-shrink-0" style="display: flex; flex-direction: column; align-items: flex-end; justify-content: center;">
                                            <div class="font-bold ${priceColor}" style="text-decoration: ${priceDecoration};">${prefix}$${parseFloat(tx.total).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                            <div class="mt-1" style="display: inline-flex; line-height: 1;">
                                                ${statusBadgeHtml}
                                            </div>
                                        </div>
                                    </div>
                                </a>`;
                            transactionsList.insertAdjacentHTML('beforeend', itemHtml);
                        });
                    }
                }

                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            })
            .catch(error => console.error('Error fetching dashboard data:', error));
        }

        $('#reportrange').daterangepicker({
            startDate: start,
            endDate: end,
            ranges: {
               'Today': [moment(), moment()],
               'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
               'Last 7 Days': [moment().subtract(6, 'days'), moment()],
               'Last 30 Days': [moment().subtract(29, 'days'), moment()],
               'This Month': [moment().startOf('month'), moment().endOf('month')],
               'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            },
            opens: 'left',
            buttonClasses: 'px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors border-transparent',
            applyButtonClasses: 'bg-indigo-600 text-white hover:bg-indigo-700',
            cancelClass: 'bg-slate-100 text-slate-600 hover:bg-slate-200',
        }, updateDateRangeDisplay);

        updateDateRangeDisplay(start, end, 'Last 30 Days');

        $('#dashboard-export-btn').on('click', function(e) {
            e.preventDefault();
            const exportUrl = `/dashboard/export?date=${start.format('YYYY-MM-DD')}&end=${end.format('YYYY-MM-DD')}`;
            window.location.href = exportUrl;
        });
    }

    // Chart.js Contexts & Instances
    const ctxMain = document.getElementById('mainChart').getContext('2d');
    const ctxPie = document.getElementById('pieChart').getContext('2d');

    // Helper to get active theme colors for Chart.js
    const getChartColors = () => {
        const isDark = document.documentElement.classList.contains('dark');
        return {
            grid: isDark ? '#334155' : '#f1f5f9', // slate-700 : slate-100
            text: isDark ? '#94a3b8' : '#64748b', // slate-400 : slate-500
            purchaseBar: isDark ? '#475569' : '#cbd5e1', // slate-600 : slate-200
            saleBar: '#4f46e5'
        };
    };

    const updateChartThemes = () => {
        const colors = getChartColors();
        if (window.mainChartInstance) {
            const chart = window.mainChartInstance;
            if (chart.options.scales.y) {
                chart.options.scales.y.grid.color = colors.grid;
                chart.options.scales.y.ticks.color = colors.text;
            }
            if (chart.options.scales.x) {
                chart.options.scales.x.ticks.color = colors.text;
            }
            if (chart.options.plugins.legend && chart.options.plugins.legend.labels) {
                chart.options.plugins.legend.labels.color = colors.text;
            }
            if (chart.data.datasets[1]) {
                chart.data.datasets[1].backgroundColor = colors.purchaseBar;
            }
            chart.update();
        }
        if (window.pieChartInstance) {
            const chart = window.pieChartInstance;
            if (chart.options.plugins.legend && chart.options.plugins.legend.labels) {
                chart.options.plugins.legend.labels.color = colors.text;
            }
            chart.update();
        }
    };

    const initialColors = getChartColors();

    window.mainChartInstance = new Chart(ctxMain, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [
                {
                    label: salesLabel,
                    data: [],
                    backgroundColor: '#4f46e5',
                    borderRadius: 6,
                    barPercentage: 0.6,
                },
                {
                    label: purchasesLabel,
                    data: [],
                    backgroundColor: initialColors.purchaseBar,
                    borderRadius: 6,
                    barPercentage: 0.6,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: { family: "'Inter', sans-serif", weight: 500 },
                        color: initialColors.text
                    }
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleFont: { family: "'Inter', sans-serif" },
                    bodyFont: { family: "'Inter', sans-serif" },
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: true,
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: initialColors.grid, drawBorder: false },
                    ticks: {
                        color: initialColors.text,
                        font: { family: "'Inter', sans-serif" },
                        callback: function(value) { return '$' + value / 1000 + 'k'; }
                    },
                    border: { dash: [4, 4], display: false }
                },
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: { color: initialColors.text, font: { family: "'Inter', sans-serif" } }
                }
            }
        }
    });

    window.pieChartInstance = new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: ['#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#94a3b8'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: { family: "'Inter', sans-serif", weight: 500 },
                        color: initialColors.text
                    }
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleFont: { family: "'Inter', sans-serif" },
                    bodyFont: { family: "'Inter', sans-serif" },
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            const value = context.raw || 0;
                            return ' ' + context.label + ': $' + Number(value).toLocaleString({minimumFractionDigits: 2, maximumFractionDigits: 2});
                        }
                    }
                }
            }
        }
    });

    // Observe HTML class changes to toggle chart themes instantly
    const themeObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === 'class') {
                updateChartThemes();
            }
        });
    });
    themeObserver.observe(document.documentElement, { attributes: true });
});
