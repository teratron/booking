import { type HandleUploadBody, handleUpload } from "@vercel/blob/client";
import type { NextRequest } from "next/server";
import { NextResponse } from "next/server";
import { getCurrentUser } from "@/lib/auth/session";

const MAX_UPLOAD_BYTES = 100 * 1024 * 1024;

// Client-upload token endpoint for the intake form's media widget (T-3B03) —
// the browser uploads bytes straight to Vercel Blob; this route only issues
// and validates the token, per the Phase 3 Decisions in tasks/phase-3.md.
export async function POST(request: NextRequest) {
	const currentUser = await getCurrentUser(request.headers);
	if (!currentUser) {
		return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
	}

	const body = (await request.json()) as HandleUploadBody;

	try {
		const jsonResponse = await handleUpload({
			body,
			request,
			onBeforeGenerateToken: async () => ({
				allowedContentTypes: ["image/*", "video/*"],
				maximumSizeInBytes: MAX_UPLOAD_BYTES,
				tokenPayload: JSON.stringify({ userId: currentUser.id }),
			}),
		});
		return NextResponse.json(jsonResponse);
	} catch (error) {
		return NextResponse.json(
			{ error: error instanceof Error ? error.message : "Upload failed" },
			{ status: 400 },
		);
	}
}
