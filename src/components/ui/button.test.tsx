import { render, screen } from "@testing-library/react";
import { expect, test } from "vitest";
import { Button } from "./button";

test("Button renders with Tailwind utility classes applied", () => {
	render(<Button>Click me</Button>);
	const button = screen.getByRole("button", { name: "Click me" });
	expect(button.className).toContain("inline-flex");
});
