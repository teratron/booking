// Livewire bundles its own Alpine.js instance and exposes it as `window.Alpine`,
// firing `alpine:init` once it boots. A separate `alpinejs` npm dependency would
// start a second, conflicting Alpine instance — every custom directive or data
// component registers through this listener instead.
document.addEventListener('alpine:init', () => {
    //
});
