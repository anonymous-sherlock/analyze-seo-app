class ErrorTracker {
  constructor(analysisResult) {
    this.data = analysisResult;
    this.issues = {
      high: 0,
      medium: 0,
      low: 0,
      passed: 0,
    };
  }

  analyze() {
    this.titleCheck(this.data?.title);
    this.descriptionCheck(this.data?.description);
    // Add more analysis checks if needed
  }

  updateUI(container, severity, length, message) {
    const infoText = container.querySelector("[info-message]");
    const icon = container.querySelector("[info-icon]");
    const box = container.querySelector("[info-box]");
    const infoLength = container.querySelector("[info-length]");

    infoLength.innerHTML = `${length} Characters`;
    icon.className = "";
    box.className = "";

    if (severity === "success") {
      icon.classList.add("an", "an-chack", "seo-icon", "seo-icon-success");
      box.classList.add("alert", "alert-success");
      this.issues.passed++;
    } else if (severity === "warning") {
      icon.classList.add("an", "an-square", "seo-icon", "seo-icon-warning");
      box.classList.add("alert", "alert-warning");
      this.issues.medium++;
    } else if (severity === "danger") {
      icon.classList.add("an", "an-triangle", "seo-icon", "seo-icon-danger");
      box.classList.add("alert", "alert-danger");
      this.issues.high++;
    }

    infoText.innerHTML = message;
  }

  checkLength(length, successMin, successMax, warningMin, warningMax) {
    if (length >= successMin && length <= successMax) {
      return "success";
    } else if (length >= warningMin && length <= warningMax) {
      return "warning";
    } else {
      return "danger";
    }
  }

  titleCheck(title) {
    const container = document.querySelector("[csi-title]");
    const length = title ? title.length : 0;

    const severity = this.checkLength(length, 45, 60, 25, 45);

    if (!title) {
      this.updateUI(
        container,
        "danger",
        "This <strong>webpage lacks a title tag!</strong> Including a title tag is crucial as it provides a concise overview of the page for search engines."
      );
    } else {
      let message = `The webpage is using a title tag with a length of <strong>${length} characters.</strong>`;

      if (severity === "warning") {
        message += ` We recommend using a title with a length between <strong>45 - 60 characters</strong> in order to fit Google Search results that have a 600-pixel limit.`;
      } else if (severity === "danger") {
        message += ` It is recommended to use well-written and engaging titles with a length between <strong>45 - 60 characters</strong>.`;
      }

      this.updateUI(container, severity, length, message);
    }
  }

  descriptionCheck(description) {
    const container = document.querySelector("[csi-description]");
    const length = description ? description.length : 0;

    const severity = this.checkLength(length, 120, 160, 80, 120);

    if (!description) {
      this.updateUI(
        container,
        "danger",
        "This page is <strong>lacking a meta description tag!</strong> This element is required to provide a brief overview of the page to search engines."
      );
    } else {
      let message = `The webpage has a meta description tag with a length of <strong>${length} characters.</strong>`;

      if (severity === "warning") {
        message += ` It is recommended to use well-written and engaging meta descriptions with a length between <strong>80 and 160 characters (including spaces).</strong>`;
      } else if (severity === "danger") {
        message += ` It is recommended to use well-written and engaging meta descriptions with a length between <strong>80 and 160 characters (including spaces)</strong>.`;
      }

      this.updateUI(container, severity, length, message);
    }
  }
}
