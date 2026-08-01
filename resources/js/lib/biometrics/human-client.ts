import type { Config, Human } from '@vladmandic/human';

const humanConfig: Partial<Config> = {
    backend: 'webgl',

    modelBasePath: '/models/human/',

    cacheModels: true,

    debug: import.meta.env.DEV,

    face: {
        enabled: true,

        detector: {
            enabled: true,
            modelPath: 'blazeface.json',
            maxDetected: 1,
            minConfidence: 0.75,
            minSize: 128,
            rotation: true,
        },

        mesh: {
            enabled: true,
            modelPath: 'facemesh.json',
        },

        description: {
            enabled: true,
            modelPath: 'faceres.json',
        },

        antispoof: {
            enabled: true,
            modelPath: 'antispoof.json',
        },

        liveness: {
            enabled: true,
            modelPath: 'liveness.json',
        },

        iris: {
            enabled: true,
            modelPath: 'iris.json',
        },

        emotion: {
            enabled: false,
        },

        attention: {
            enabled: false,
        },

        gear: {
            enabled: false,
        },
    },

    body: {
        enabled: false,
    },

    hand: {
        enabled: false,
    },

    object: {
        enabled: false,
    },

    segmentation: {
        enabled: false,
    },

    gesture: {
        enabled: true,
    },
};

let human: Human | null = null;

let humanLoadPromise: Promise<Human> | null = null;

/**
 * Membuat instance Human hanya ketika fitur biometrik
 * benar-benar mulai digunakan.
 */
async function createHuman(): Promise<Human> {
    const humanModule = await import('@vladmandic/human');

    return new humanModule.Human(humanConfig);
}

/**
 * Mengambil instance Human yang sudah siap dipakai.
 */
export async function prepareHuman(): Promise<Human> {
    if (!humanLoadPromise) {
        humanLoadPromise = (async (): Promise<Human> => {
            const instance = human ?? (human = await createHuman());

            await instance.load();

            return instance;
        })().catch((error: unknown) => {
            human = null;
            humanLoadPromise = null;

            throw error;
        });
    }

    return humanLoadPromise;
}

/**
 * Mengambil instance Human tanpa memuat ulang model.
 */
export function getHumanInstance(): Human {
    if (!human) {
        throw new Error('Human belum disiapkan. Panggil prepareHuman() terlebih dahulu.');
    }

    return human;
}
