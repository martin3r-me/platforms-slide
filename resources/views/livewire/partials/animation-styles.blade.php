@once
<style>
    /* Slide Transitions */
    @keyframes slideInLeft { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes slideInRight { from { transform: translateX(-100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes slideInUp { from { transform: translateY(100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes zoomIn { from { transform: scale(0.85); opacity: 0; } to { transform: scale(1); opacity: 1; } }

    .slide-transition-fade { animation: fadeIn 0.5s ease-out; }
    .slide-transition-slide-left { animation: slideInLeft 0.4s ease-out; }
    .slide-transition-slide-right { animation: slideInRight 0.4s ease-out; }
    .slide-transition-slide-up { animation: slideInUp 0.4s ease-out; }
    .slide-transition-zoom { animation: zoomIn 0.4s ease-out; }

    /* Element Animations */
    @keyframes fadeInUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    @keyframes fadeInLeft { from { transform: translateX(-30px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes scaleIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }

    .el-anim-fadeInUp { animation: fadeInUp 0.6s ease-out both; }
    .el-anim-fadeInLeft { animation: fadeInLeft 0.6s ease-out both; }
    .el-anim-scaleIn { animation: scaleIn 0.5s ease-out both; }
</style>
@endonce
