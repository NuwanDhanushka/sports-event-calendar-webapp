import { ref, onMounted } from 'vue'

const THEME_KEY = 'theme'
const LIGHT = 'sport'
const DARK  = 'sport-dark'

export function useTheme() {
    const theme = ref(LIGHT)

    const apply = (t) => {
        theme.value = t
        document.documentElement.setAttribute('data-theme', t)
        localStorage.setItem(THEME_KEY, t)
    }

    const toggle = () => apply(theme.value === DARK ? LIGHT : DARK)

    onMounted(() => {
        const saved = localStorage.getItem(THEME_KEY)
        if (saved === LIGHT || saved === DARK) {
            apply(saved)
        } else {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
            apply(prefersDark ? DARK : LIGHT)
        }
    })

    return { theme, toggle, LIGHT, DARK }
}