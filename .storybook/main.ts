import { resolve } from 'node:path';
import type { StorybookConfig } from '@storybook/react-vite';

const config: StorybookConfig = {
    stories: ['../resources/js/**/*.stories.@(ts|tsx)'],
    addons: ['@storybook/addon-essentials', '@storybook/addon-interactions', '@storybook/addon-links'],
    framework: {
        name: '@storybook/react-vite',
        options: {},
    },
    docs: {
        autodocs: 'tag',
    },
    typescript: {
        reactDocgen: 'react-docgen-typescript',
    },
    viteFinal: async (config) => {
        config.resolve ??= {};
        config.resolve.alias = {
            ...(config.resolve.alias ?? {}),
            '@': resolve(__dirname, '../resources/js'),
        };
        return config;
    },
};

export default config;
