export type Data = Record<string, unknown>;

export type SharedProps = Data & {
    auth?: {
        user?: {
            first_name?: string;
            last_name?: string;
            permissions?: Record<string, boolean>;
        } | null;
    };
    errors?: Record<string, string>;
    flash?: {
        success?: string;
        scan?: { action?: string; at?: string };
        scan_reason?: string;
    };
};
