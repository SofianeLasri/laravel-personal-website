import prettier from 'eslint-config-prettier';
import vue from 'eslint-plugin-vue';

import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';

export default defineConfigWithVueTs(
    vue.configs['flat/recommended'], // Changed from 'essential' to 'recommended' for stricter Vue rules
    vueTsConfigs.recommendedTypeChecked, // Changed to include type checking
    {
        ignores: [
            'vendor',
            'node_modules',
            'public',
            'bootstrap/ssr',
            'tailwind.config.js',
            'resources/js/ziggy.js',
            'resources/js/components/ui/*',
            'storage',
            'bootstrap/cache',
            '*.min.js',
            'dist',
            'build',
        ],
    },
    {
        languageOptions: {
            parserOptions: {
                project: true,
                tsconfigRootDir: import.meta.dirname,
            },
        },
        rules: {
            // TypeScript Rules - Strict
            '@typescript-eslint/no-explicit-any': 'warn', // Changed from 'off' to 'warn'
            '@typescript-eslint/explicit-function-return-type': 'off', // Too strict for Vue components
            '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
            '@typescript-eslint/no-non-null-assertion': 'warn',
            '@typescript-eslint/strict-boolean-expressions': 'off', // Too strict for practical use
            '@typescript-eslint/no-floating-promises': 'error',
            '@typescript-eslint/no-misused-promises': 'error',
            '@typescript-eslint/await-thenable': 'error',
            '@typescript-eslint/no-unnecessary-type-assertion': 'warn',
            '@typescript-eslint/prefer-nullish-coalescing': 'warn',
            '@typescript-eslint/prefer-optional-chain': 'warn',
            '@typescript-eslint/no-unsafe-assignment': 'off', // Often too strict with third-party libs
            '@typescript-eslint/no-unsafe-member-access': 'off',
            '@typescript-eslint/no-unsafe-call': 'off',
            '@typescript-eslint/no-unsafe-return': 'off',
            '@typescript-eslint/no-unsafe-argument': 'off',

            // Vue Rules - Strict
            'vue/multi-word-component-names': 'off', // Keep off for single-word components
            'vue/require-default-prop': 'error',
            'vue/require-prop-types': 'error',
            'vue/require-v-for-key': 'error',
            'vue/no-v-html': 'warn', // Security warning
            'vue/component-tags-order': [
                'error',
                {
                    order: ['script', 'template', 'style'],
                },
            ],
            'vue/block-tag-newline': 'error',
            'vue/component-name-in-template-casing': ['error', 'PascalCase'],
            'vue/custom-event-name-casing': ['error', 'kebab-case'],
            'vue/html-self-closing': [
                'error',
                {
                    html: {
                        void: 'always',
                        normal: 'never',
                        component: 'always',
                    },
                },
            ],
            'vue/no-unused-refs': 'error',
            'vue/no-unused-vars': 'error',
            'vue/no-use-v-if-with-v-for': 'error',
            'vue/prefer-import-from-vue': 'error',
            'vue/prefer-separate-static-class': 'error',
            'vue/prefer-template': 'error',
            'vue/require-macro-variable-name': 'error',
            'vue/valid-v-slot': 'error',
            'vue/v-on-event-hyphenation': 'error',
            'vue/attribute-hyphenation': 'error',
            'vue/html-quotes': ['error', 'double'],
            'vue/mustache-interpolation-spacing': ['error', 'always'],
            'vue/no-multi-spaces': 'error',
            'vue/no-spaces-around-equal-signs-in-attribute': 'error',
            'vue/prop-name-casing': ['error', 'camelCase'],
            'vue/v-bind-style': ['error', 'shorthand'],
            'vue/v-on-style': ['error', 'shorthand'],
            'vue/attributes-order': [
                'error',
                {
                    order: [
                        'DEFINITION',
                        'LIST_RENDERING',
                        'CONDITIONALS',
                        'RENDER_MODIFIERS',
                        'GLOBAL',
                        'UNIQUE',
                        'SLOT',
                        'TWO_WAY_BINDING',
                        'OTHER_DIRECTIVES',
                        'OTHER_ATTR',
                        'EVENTS',
                        'CONTENT',
                    ],
                },
            ],
            'vue/no-lone-template': 'error',
            'vue/this-in-template': ['error', 'never'],
            'vue/no-duplicate-attributes': [
                'error',
                {
                    allowCoexistClass: true,
                    allowCoexistStyle: true,
                },
            ],

            // General JavaScript/TypeScript Rules
            'no-console': ['warn', { allow: ['warn', 'error'] }],
            'no-debugger': 'error',
            'no-alert': 'warn',
            'no-unused-expressions': 'error',
            'prefer-const': 'error',
            'no-var': 'error',
            'object-shorthand': 'error',
            'prefer-template': 'error',
            'no-param-reassign': ['error', { props: false }],
            'array-callback-return': 'error',
            'consistent-return': 'warn',
            curly: ['error', 'all'],
            'default-case': 'warn',
            'dot-notation': 'error',
            eqeqeq: ['error', 'always'],
            'no-else-return': 'error',
            'no-empty-function': 'warn',
            'no-implicit-coercion': 'error',
            'no-invalid-this': 'error',
            'no-loop-func': 'error',
            'no-multi-str': 'error',
            'no-new': 'warn',
            'no-return-assign': 'error',
            'no-return-await': 'error',
            'no-self-compare': 'error',
            'no-sequences': 'error',
            'no-throw-literal': 'error',
            'no-unmodified-loop-condition': 'error',
            'no-useless-call': 'error',
            'no-useless-concat': 'error',
            'no-useless-return': 'error',
            'prefer-promise-reject-errors': 'error',
            radix: 'error',
            'require-await': 'error',
            yoda: 'error',
        },
    },
    prettier,
);
