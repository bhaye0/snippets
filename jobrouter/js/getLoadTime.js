function getLoadTime() {
    const end = window.performance.timing.domContentLoadedEventEnd;
    const start = window.performance.timing.navigationStart;
    return end - start;
}