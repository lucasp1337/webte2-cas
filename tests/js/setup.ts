import '@testing-library/jest-dom';

const localStorageStore: Record<string, string> = {};
const localStorageMock: Storage = {
    getItem: (key: string) => localStorageStore[key] ?? null,
    setItem: (key: string, value: string) => {
        localStorageStore[key] = value;
    },
    removeItem: (key: string) => {
        delete localStorageStore[key];
    },
    clear: () => {
        for (const key of Object.keys(localStorageStore)) {
            delete localStorageStore[key];
        }
    },
    key: (index: number) => Object.keys(localStorageStore)[index] ?? null,
    get length() {
        return Object.keys(localStorageStore).length;
    },
};

Object.defineProperty(window, 'localStorage', {
    value: localStorageMock,
    writable: true,
});

Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: (query: string): MediaQueryList => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: () => undefined,
        removeListener: () => undefined,
        addEventListener: () => undefined,
        removeEventListener: () => undefined,
        dispatchEvent: () => false,
    }),
});
