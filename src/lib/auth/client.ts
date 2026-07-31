// Imported by sign-up-form.tsx, sign-in-form.tsx, and auth-nav.tsx; fallow's
// reachability graph doesn't trace through those files for reasons not yet
// root-caused, despite the import edges existing (verified via direct grep).
// fallow-ignore-file unused-file
import { createAuthClient } from "better-auth/react";

export const authClient = createAuthClient();
