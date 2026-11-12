import { BarController, BarElement, CategoryScale, Chart, LinearScale, Tooltip } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

// Chart chrome, kept quiet so only the data stands out (warm greys of the theme)
const INK = '#1f1c1a';
const INK_MUTED = '#776f67';
const GRID = '#f1ece6';
const AXIS = '#d9d1c8';
// Hind Siliguri draws the taka sign, Sora has no Bengali glyphs
const FONT = "Sora, 'Hind Siliguri', system-ui, sans-serif";

const taka = (value) => '৳' + Math.round(value).toLocaleString('en-IN');

const compactTaka = (value) => {
    if (value >= 100000) return '৳' + (value / 100000).toFixed(value % 100000 === 0 ? 0 : 1) + 'L';
    if (value >= 1000) return '৳' + (value / 1000).toFixed(value % 1000 === 0 ? 0 : 1) + 'K';
    return '৳' + value;
};

// Writes the value on the cap of the newest month's columns only,
// so the latest figures can be read without hovering.
const latestValueLabels = {
    id: 'latestValueLabels',
    afterDatasetsDraw(chart) {
        const { ctx } = chart;
        const lastIndex = chart.data.labels.length - 1;
        ctx.save();
        ctx.font = `600 11px ${FONT}`;
        ctx.fillStyle = INK;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'bottom';
        chart.data.datasets.forEach((dataset, i) => {
            const bar = chart.getDatasetMeta(i).data[lastIndex];
            const value = dataset.data[lastIndex];
            if (bar && value > 0) {
                ctx.fillText(compactTaka(value), bar.x, bar.y - 4);
            }
        });
        ctx.restore();
    },
};

function columnChart(canvas, config) {
    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels: config.labels,
            datasets: config.series.map((series) => ({
                label: series.name,
                data: series.data,
                backgroundColor: series.color,
                hoverBackgroundColor: series.color,
                maxBarThickness: 24,
                borderRadius: { topLeft: 4, topRight: 4 },
                borderSkipped: 'start',
                categoryPercentage: 0.62,
                barPercentage: 0.86,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 350 },
            layout: { padding: { top: 22 } },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#ffffff',
                    titleColor: INK,
                    bodyColor: INK_MUTED,
                    borderColor: '#e9e3dc',
                    borderWidth: 1,
                    padding: 10,
                    boxPadding: 4,
                    usePointStyle: true,
                    titleFont: { family: FONT, weight: '600' },
                    bodyFont: { family: FONT },
                    callbacks: {
                        label: (item) => ` ${item.dataset.label}: ${taka(item.raw)}`,
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { color: AXIS },
                    ticks: { color: INK_MUTED, font: { family: FONT, size: 11 } },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: GRID },
                    border: { display: false },
                    ticks: {
                        color: INK_MUTED,
                        font: { family: FONT, size: 11 },
                        maxTicksLimit: 5,
                        callback: (value) => compactTaka(value),
                    },
                },
            },
        },
        plugins: [latestValueLabels],
    });
}

export function mountCharts() {
    document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        try {
            const config = JSON.parse(canvas.dataset.chart);
            if (config.type === 'columns') {
                columnChart(canvas, config);
            }
        } catch (error) {
            console.error('Chart could not be drawn', error);
        }
    });
}
