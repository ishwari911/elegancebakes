document.addEventListener("DOMContentLoaded", () => {
  const fallback = "default-image.png";

  document.querySelectorAll("img").forEach((img) => {
    const original = img.getAttribute("src"); // keep attribute value

    img.addEventListener("error", () => {
      if (img.getAttribute("src") === fallback) return; // loop guard
      img.setAttribute("src", fallback);
    });

    // Re-trigger after handler is attached
    if (original) {
      img.setAttribute("src", "");
      img.setAttribute("src", original);
    }
  });
});
