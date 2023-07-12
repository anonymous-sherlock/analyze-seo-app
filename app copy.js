class ErrorTracker {
  constructor(analysisResult) {
    this.data = analysisResult;
    this.issues = {
      SEO: {
        high: 0,
        medium: 0,
        low: 0,
        passed: 0,
      },
      "Speed Optimizations": {
        high: 0,
        medium: 0,
        low: 0,
        passed: 0,
      },
      "Server and Security": {
        high: 0,
        medium: 0,
        low: 0,
        passed: 0,
      },
      Advance: {
        high: 0,
        medium: 0,
        low: 0,
        passed: 0,
      },
    };
  }

  analyze() {
    this.titleCheck(this.data?.title);
    this.descriptionCheck(this.data?.description);
    this.headingCheck(this.data?.headings);
    this.custom404PageCheck(this.data?.hasCustom404Page);
    this.imageAltTextCheck(
      this.data?.imagesWithoutAltText,
      this.data?.totalImageCount
    );

    this.languageCheck(this.data?.language);
    this.faviconCheck(this.data?.favicon);
    this.robotsTxtCheck(this.data?.hasRobotsTxt);
    // Add more analysis checks if needed
  }

  updateCategoryCounts(category, severity) {
    this.categories[category][severity]++;
  }
  updateIssueCounts(severity, category) {
    if (issue in this.issues) {
      this.updateCategoryCounts(issue, severity);
    }
  }

  updateUI(container, severity, message, length) {
    const infoText = container.querySelector("[info-message]");
    const icon = container.querySelector("[info-icon]");
    const box = container.querySelector("[info-box]");
    const infoLength = container.querySelector("[info-length]");

    icon.className = "";
    box.className = "";

    if (severity === "success") {
      icon.classList.add("an", "an-chack", "seo-icon", "seo-icon-success");
      this.issues.passed++;
    } else if (severity === "warning") {
      icon.classList.add("an", "an-square", "seo-icon", "seo-icon-warning");
      this.issues.medium++;
    } else if (severity === "danger") {
      icon.classList.add("an", "an-triangle", "seo-icon", "seo-icon-danger");
      this.issues.high++;
    } else {
      icon.classList.add("an", "an-circle", "seo-icon", "seo-icon-info");
      this.updateIssueCounts("high", category);
    }

    infoText.innerHTML = message;

    if (length) {
      infoLength.innerHTML = `${length} Characters`;
      box.style.display = "block";
    } else {
      infoLength.innerHTML = "0 Characters";
      box.style.display = "none";
    }

    if (box) {
      box.classList.add("alert");
      if (severity === "success") {
        box.classList.add("alert-success");
      } else if (severity === "warning") {
        box.classList.add("alert-warning");
      } else if (severity === "danger") {
        box.classList.add("alert-danger");
      }
    }
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

      this.updateUI(container, severity, message, length);
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
        "This webpage is <strong>missing a meta description tag</strong>! It is important to include this tag in order to provide a brief summary of the page for search engines. A well-written and enticing meta description can also increase the number of clicks on your site in search engine results."
      );
    } else {
      let message = `The webpage has a meta description tag with a length of <strong>${length} characters.</strong>`;

      if (severity === "warning") {
        message += ` It is recommended to use well-written and engaging meta descriptions with a length between <strong>80 and 160 characters (including spaces).</strong>`;
      } else if (severity === "danger") {
        message += ` It is recommended to use well-written and engaging meta descriptions with a length between <strong>80 and 160 characters (including spaces)</strong>.`;
      }

      this.updateUI(container, severity, message, length);
    }
  }

  headingCheck(headings) {
    const container = document.querySelector("[csi-headings]");
    const icon = container.querySelector("[info-icon]");
    const infoMessage = container.querySelector("[info-message]");
    infoMessage.innerHTML = "";

    const h1Count = headings?.h1.length || 0;
    const h2Count = headings?.h2.length || 0;
    const h3Count = headings?.h3.length || 0;

    icon.className = "";

    if (h1Count > 1) {
      infoMessage.innerHTML =
        "<strong>Multiple h1 tags found</strong> on the webpage. It is recommended to have <strong>only one h1 tag</strong> for proper heading structure.";
      icon.classList.add("an", "an-triangle", "seo-icon", "seo-icon-danger");
    } else if (h1Count === 0) {
      infoMessage.innerHTML =
        "<strong>No h1 tag found on the webpage</strong>. It is recommended to <strong>include an h1 tag</strong> to provide a clear and descriptive heading for search engines.";
      icon.classList.add("an", "an-triangle", "seo-icon", "seo-icon-danger");
    } else if (h2Count === 0) {
      infoMessage.innerHTML =
        "<strong>No h2 tag found on the webpage</strong>. It is recommended to <strong>include an h2 tag</strong> for better organization and hierarchical structure.";
      icon.classList.add("an", "an-square", "seo-icon", "seo-icon-warning");
    } else if (h3Count === 0) {
      infoMessage.innerHTML =
        "<strong>No h3 tag found on the webpage</strong>. Consider using h3 tags to provide subheadings and further organize your content.";
      icon.classList.add("an", "an-circle", "seo-icon", "seo-icon-info");
    } else {
      infoMessage.innerHTML =
        "All heading tags are <strong>well-structured and properly used</strong>.";
      icon.classList.add("an", "an-chack", "seo-icon", "seo-icon-success");
    }
  }

  custom404PageCheck(custom404Page) {
    const container = document.querySelector("[csi-404-page]");
    const icon = container.querySelector("[info-icon]");
    const infoMessage = container.querySelector("[info-message]");
    infoMessage.innerHTML = "";

    icon.className = "";

    if (custom404Page) {
      infoMessage.innerHTML =
        "This website <strong>has a custom 404 error page</strong>. It is recommended to have a custom 404 error page to improve the user experience for your website by letting users know that only a specific page is missing or broken (not the entire site). You can also provide helpful links, the opportunity to report bugs, and potentially track the source of broken links.";
      icon.classList.add("an", "an-chack", "seo-icon", "seo-icon-success");
    } else {
      infoMessage.innerHTML =
        "This website <strong>does not have a custom 404 error page</strong>. It is recommended to create a custom 404 error page to improve the user experience and provide helpful information to visitors when they encounter broken or missing pages.";
      icon.classList.add("an", "an-triangle", "seo-icon", "seo-icon-danger");
    }
  }

  imageAltTextCheck(imagesWithoutAltText, totalImageCount) {
    const container = document.querySelector("[csi-image-alt-text]");
    const icon = container.querySelector("[info-icon]");
    const infoMessage = container.querySelector("[info-message]");
    icon.className = "";

    if (!imagesWithoutAltText.length) {
      infoMessage.innerHTML = `All images on the webpage <strong>have proper "alt" text</strong>, enhancing both accessibility and SEO optimization.`;
      icon.classList.add("an", "an-chack", "seo-icon", "seo-icon-success");
    } else if (imagesWithoutAltText.length < 2) {
      infoMessage.innerHTML = `This webpage is using ${totalImageCount} images and <strong>${imagesWithoutAltText.length} tags are empty or missing "alt" attribute!</strong>. It is recommended to provide descriptive "alt" text for better accessibility and SEO optimization.`;
      icon.classList.add("an", "an-circle", "seo-icon", "seo-icon-info");
    } else if (imagesWithoutAltText.length < 5) {
      infoMessage.innerHTML = `This webpage is using ${totalImageCount} images and <strong>${imagesWithoutAltText.length} tags are empty or missing "alt" attribute!</strong>. It is important to add descriptive "alt" text to improve accessibility and SEO optimization.`;
      icon.classList.add("an", "an-square", "seo-icon", "seo-icon-warning");
    } else {
      infoMessage.innerHTML = `
      This webpage is using ${totalImageCount} "img" tags with <strong>${imagesWithoutAltText.length} tags are empty or missing "alt" attribute!</strong>. Adding descriptive "alt" text to these images is crucial for accessibility and SEO optimization.`;
      icon.classList.add("an", "an-triangle", "seo-icon", "seo-icon-danger");
    }
  }

  languageCheck(language) {
    const container = document.querySelector("[csi-language]");
    const icon = container.querySelector("[info-icon]");
    const infoMessage = container.querySelector("[info-message]");
    icon.className = "";

    if (language) {
      infoMessage.innerHTML = `The <strong>lang</strong> attribute is present in the HTML tag with a value of`;
      icon.classList.add("an", "an-chack", "seo-icon", "seo-icon-success");
    } else {
      infoMessage.innerHTML = `This webpage has not language declared. It is recommended to include the <code>lang</code> attribute to specify the language of the webpage.`;
      icon.classList.add("an", "an-square", "seo-icon", "seo-icon-warning");
    }
  }
  faviconCheck(favicon) {
    const container = document.querySelector("[csi-favicon]");
    const icon = container.querySelector("[info-icon]");
    const infoMessage = container.querySelector("[info-message]");
    icon.className = "";
    if (favicon) {
      infoMessage.innerHTML = `This website appears to have a <strong>Favicon</strong>.`;
      icon.classList.add("an", "an-chack", "seo-icon", "seo-icon-success");
    } else {
      infoMessage.innerHTML = `This webpage is missing a <strong>favicon</strong>. It is recommended to include a favicon, which is a small icon that represents your website and appears in browser tabs and bookmarks. Having a favicon enhances the visual identity of your website.`;
      icon.classList.add("an", "an-square", "seo-icon", "seo-icon-warning");
    }
  }

  robotsTxtCheck(hasRobotsTxt) {
    const container = document.querySelector("[csi-robots-txt]");
    const icon = container.querySelector("[info-icon]");
    const infoMessage = container.querySelector("[info-message]");
    icon.className = "";
    if (hasRobotsTxt) {
      infoMessage.innerHTML = `This website has a "<strong>robots.txt</strong>" file.`;
      icon.classList.add("an", "an-chack", "seo-icon", "seo-icon-success");
    } else {
      infoMessage.innerHTML = `This website is <strong>missing a "robots.txt"</strong> file. This file can protect private content from appearing online, save bandwidth, and lower load time on your server. Creating and properly configuring a robots.txt file can help control the visibility of your website's content in search engines.`;
      icon.classList.add("an", "an-triangle", "seo-icon", "seo-icon-danger");
    }
  }
}
