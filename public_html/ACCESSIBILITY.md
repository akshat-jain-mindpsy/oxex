# E-Log Accessibility Improvement Plan

This document outlines the key accessibility challenges within the e-log application. The goal is to identify and define the barriers that affect users with disabilities, particularly those who rely on assistive technologies. This includes screen readers like **Narrator** and **VoiceOver**, screen magnifiers, and keyboard-only navigation, as detailed in accessibility resources from Microsoft and others.

---

## Identified Accessibility Issues: Problem Statements

### 1. Impeded Visual Access & Magnification
**Problem:** The application actively prevents users from zooming in to magnify content. Many pages use the meta tag `maximum-scale=1`, which disables the browser's pinch-to-zoom and other scaling features. This creates a significant barrier for users with low vision who rely on screen magnification to read content, a core feature mentioned in the Windows accessibility suite [[1]](https://support.microsoft.com/en-gb/windows/discover-windows-accessibility-features-8b1068e6-d3b8-4ba8-b027-133dd8911df9).

### 2. Lack of Visible and Logical Keyboard Navigation
**Problem:** The application has two key issues for users who rely on a keyboard:
*   **Invisible Focus:** The codebase may contain CSS rules (`outline: none;`) that remove the visual focus indicator. Without it, sighted keyboard users cannot tell where they are on the page.
*   **Inefficient Navigation:** On every page, keyboard and screen reader users are forced to tab through the entire header and navigation menu before reaching the main content. This is repetitive and inefficient. A "Skip to Content" link is needed to bypass these recurring blocks.

### 3. Inadequate Support for Screen Readers (Narrator, VoiceOver)
**Problem:** The application presents several major obstacles for users of screen readers, making it difficult or impossible to understand and operate the interface.
*   **Incoherent Page Structure:** The application often uses non-semantic HTML (`<div>` tags) for critical layout elements. For a screen reader, this is like reading a document with no headings or chapters, making it difficult to understand the page's layout and navigate efficiently [[2]](https://accessibility.education.gov.uk/knowledge-hub/screen-readers).
*   **Uninformative Images:** Images that convey information must have descriptive alternative text (`alt` text). If they do not, this information is completely lost to anyone using a screen reader.
*   **Ambiguous Forms:** Form input fields may not be programmatically linked to their labels. When a `<label>` is not correctly associated with its `<input>`, a screen reader user has no context for what information they are being asked to provide.

### 4. Insufficient Color Contrast
**Problem:** For users with low vision or color blindness, text can be difficult or impossible to read if there isn't enough contrast between it and the background. The application's color palette needs to be evaluated against Web Content Accessibility Guidelines (WCAG) contrast ratios to ensure it is compliant with standards like those mentioned by Microsoft [[1]](https://support.microsoft.com/en-gb/windows/discover-windows-accessibility-features-8b1068e6-d3b8-4ba8-b027-133dd8911df9).

### 5. Missing Captions and Audio Alternatives
**Problem:** The information from Microsoft's accessibility features highlights support for users with hearing impairments, such as live captions [[1]](https://support.microsoft.com/en-gb/windows/discover-windows-accessibility-features-8b1068e6-d3b8-4ba8-b027-133dd8911df9). If the application uses video or audio content, it must provide alternatives like captions or transcripts to be accessible to users who are deaf or hard of hearing.

---
### References
[1] Microsoft. (n.d.). *Discover Windows accessibility features*. Retrieved from https://support.microsoft.com/en-gb/windows/discover-windows-accessibility-features-8b1068e6-d3b8-4ba8-b027-133dd8911df9
[2] Department for Education. (2024). *Screen readers*. GOV.UK. Retrieved from https://accessibility.education.gov.uk/knowledge-hub/screen-readers

## 1. Visual Accessibility

### 1.1. Enable Zooming and Scaling

**Problem:** Many pages across the application, especially within the `oxex-admin` area, contain `<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">`. The `maximum-scale=1` attribute prevents users from zooming in, which is a critical barrier for users with low vision.

**Action:**
- **Task:** Systematically remove `maximum-scale=1` and `user-scalable=no` from all `meta` viewport tags across the application.
- **Priority:** High. This is a simple change that provides a significant accessibility win.

### 1.2. Ensure Visible Keyboard Focus

**Problem:** Keyboard users rely on a visible focus indicator (like an outline) to see which element is currently active. It's a common but problematic practice to remove this outline with CSS rules like `outline: none;` without providing a clear alternative.

**Action:**
- **Task:** Audit all CSS files for `outline: none` or `outline: 0`.
- **Task:** If this rule is present, replace it with a custom, highly-visible focus style that is consistent with the site's design. For example: `a:focus, button:focus, input:focus { outline: 2px solid #005A9C; outline-offset: 2px; }`
- **Priority:** High. Without a visible focus, keyboard navigation is nearly impossible.

## 2. Screen Reader and Keyboard Navigation

### 2.1. Semantic HTML Structure

**Problem:** For screen readers to interpret a page correctly, the HTML must be semantically structured. Using non-descriptive `<div>` tags for everything makes it difficult for users to understand the layout and navigate between sections.

**Action:**
- **Task:** Review key templates and pages to replace non-semantic `<div>` elements with appropriate HTML5 tags:
    - `<header>` for the top banner and site-wide branding.
    - `<nav>` for main navigation menus.
    - `<main>` to wrap the primary content of the page.
    - `<footer>` for the page footer.
    - `<section>`, `<article>`, and `<aside>` for content sections.
- **Priority:** Medium. This requires more effort but fundamentally improves the experience for screen reader users.

### 2.2. Image Accessibility

**Problem:** Images without descriptive `alt` text are invisible to screen reader users.

**Action:**
- **Task:** Audit all `<img>` tags.
- **Task:** Add descriptive `alt` text that conveys the meaning and context of the image.
- **Task:** For purely decorative images, use an empty `alt` attribute (`alt=""`) so screen readers can ignore them.
- **Priority:** Medium.

### 2.3. Form Accessibility

**Problem:** Forms can be a major challenge if not marked up correctly. Users need to know what each field is for and whether their input is valid.

**Action:**
- **Task:** Ensure every `<input>`, `<textarea>`, and `<select>` element has an associated `<label>`. The `for` attribute of the label should match the `id` of the input.
- **Task:** Ensure form validation errors are programmatically linked to the invalid fields and clearly announced to the user.
- **Priority:** High, especially for login and data entry forms.

## 3. Testing and Validation

To ensure these changes are effective, we should incorporate regular accessibility testing into the development process.

**Actions:**
1.  **Manual Keyboard Testing:** Regularly navigate the site using only the keyboard (Tab, Shift+Tab, Enter, Spacebar). Ensure all interactive elements are reachable and operable.
2.  **Screen Reader Testing:** Test key user flows using built-in screen readers like VoiceOver (macOS/iOS) or Narrator (Windows).
3.  **Automated Tools:** Use browser extensions like **axe DevTools** or **WAVE** to run automated scans and catch common issues.

By following this plan, we can make the e-log significantly more usable for people with diverse needs and abilities.
