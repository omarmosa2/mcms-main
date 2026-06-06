import { Buffer } from 'node:buffer';

const decodeBase64 = (value: string): string =>
    Buffer.from(value, 'base64').toString('binary');
const encodeBase64 = (value: string): string =>
    Buffer.from(value, 'binary').toString('base64');

Object.defineProperty(globalThis, 'atob', {
    configurable: true,
    value: decodeBase64,
});

Object.defineProperty(globalThis, 'btoa', {
    configurable: true,
    value: encodeBase64,
});

if (typeof window !== 'undefined') {
    Object.defineProperty(window, 'atob', {
        configurable: true,
        value: decodeBase64,
    });

    Object.defineProperty(window, 'btoa', {
        configurable: true,
        value: encodeBase64,
    });
}
