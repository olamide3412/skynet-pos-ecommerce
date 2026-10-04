import { ref, onMounted } from 'vue';

export function useTheme() {
    const theme = ref('system'); // 'light', 'dark', 'system'

    const initTheme = () => {
        const storedTheme = localStorage.getItem('theme');
        if (storedTheme) {
            theme.value = storedTheme;
        }
        applyTheme();
    };

    const applyTheme = () => {
        const isDark = theme.value === 'dark' || 
            (theme.value === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            
        if (isDark) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    };

    const toggleTheme = () => {
        if (theme.value === 'light') theme.value = 'dark';
        else if (theme.value === 'dark') theme.value = 'system';
        else theme.value = 'light';
        
        localStorage.setItem('theme', theme.value);
        applyTheme();
    };

    onMounted(() => {
        initTheme();
        
        // Listen for system preference changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            if (theme.value === 'system') {
                applyTheme();
            }
        });
    });

    return {
        theme,
        toggleTheme
    };
}
