// ESLint flat config for the production JavaScript of the module (RequireJS / AMD modules under view/).
import globals from 'globals';

export default [
    {
        ignores: ['vendor/**', 'node_modules/**', 'build/**', 'test-results/**', 'Test/**', 'e2e/**', 'dev/**']
    },
    {
        files: ['view/**/*.js'],
        languageOptions: {
            ecmaVersion: 2020,
            sourceType: 'script',
            globals: {
                ...globals.browser,
                define: 'readonly',
                require: 'readonly',
                $t: 'readonly'
            }
        },
        rules: {
            'no-var': 'error',
            'prefer-const': 'error',
            'no-console': 'error',
            'no-eval': 'error',
            'no-alert': 'error',
            'no-implied-eval': 'error',
            eqeqeq: ['error', 'smart'],
            'guard-for-in': 'error',
            'no-undef': 'error',
            'no-unused-vars': 'warn',
            indent: ['error', 4],
            semi: ['error', 'always'],
            'eol-last': ['error', 'always'],
            'no-trailing-spaces': 'error',
            'max-len': ['warn', 120],
            'comma-dangle': ['error', 'never'],
            curly: ['error', 'all'],
            'no-lonely-if': 'error',
            'no-else-return': 'error',
            'no-loop-func': 'error'
        }
    },
    {
        // requirejs-config.js declares a global `var config` that RequireJS reads
        files: ['**/requirejs-config.js'],
        rules: {
            'no-var': 'off',
            'no-undef': 'off',
            'no-unused-vars': 'off'
        }
    }
];
