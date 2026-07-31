import { Human, type Config } from '@vladmandic/human';

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

const human = new Human(humanConfig);

let humanLoadPromise: Promise<Human> | null = null;

/**
 * Mengambil instance Human yang sudah siap dipakai.
 */
export async function prepareHuman(): Promise<Human> {
    if (!humanLoadPromise) {
        humanLoadPromise = (async (): Promise<Human> => {
            await human.load();

            return human;
        })().catch((error: unknown) => {
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
    return human;
}
