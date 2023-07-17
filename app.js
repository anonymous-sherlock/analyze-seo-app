class ErrorTracker {
  constructor(analysisResult) {
    this.data = analysisResult;
    this.categories = {
      SEO: {},
      Performance: {},
      "Server and Security": {},
      Advance: {},
    };
  }

  analyze() {
    this.analyzeSEO();
    this.analyzePerformance();
  }

  analyzeSEO() {
    const badge = document.querySelector("#seo");
    const cat = "SEO";

    this.checkTitle(cat);
    this.checkDescription(cat);
    this.checkHeadings(cat);
    this.checkCustom404Page(cat);
    this.checkImageAltText(cat);
    this.checkLanguage(cat);
    this.checkFavicon(cat);
    this.checkRobotsTxt(cat);
    this.checkNoFollowTag(cat);
    this.checkNoIndexTag(cat);
    this.checkSPFRecord(cat);
    this.checkURLRedirect(cat);
    this.nonSEOFriendlyURLCheck(cat);

    console.log(this.categories.SEO);
    this.renderBadge(badge, this.categories.SEO);
  }

  analyzePerformance() {
    const badge = document.querySelector("#performance");
    const cat = "Performance";

    this.checkDOMSize(cat);
    this.checkLoadTime(cat);
    this.checkPageSize(cat);
    this.checkHttpRequests(cat);

    console.log(this.categories.Performance);
    this.renderBadge(badge, this.categories.Performance);
  }

  renderBadge(selector, cat) {
    const reportBadge = selector.querySelector(".report-badges");

    // Clear existing badges
    reportBadge.innerHTML = "";

    // Map the category names to their corresponding labels and order
    const categoryData = [
      { name: "danger", label: "High Issue", order: 1 },
      { name: "warning", label: "Medium Issue", order: 2 },
      { name: "info", label: "Low Issue", order: 3 },
      { name: "success", label: "Test Passed", order: 4 },
    ];

    // Sort the categories based on the specified order
    const sortedCategories = categoryData.sort((a, b) => a.order - b.order);

    // Iterate over the sorted categories
    for (const category of sortedCategories) {
      const severity = category.name;
      const count = cat[severity];

      // Check if the category is present in the cat object and count is greater than 0
      if (count !== undefined && count > 0) {
        // Create the badge element
        const badge = document.createElement("span");
        badge.classList.add("badge", `badge-${severity}`, "ms-1");

        // Determine the label based on the severity
        const label = category.label;

        // Determine the text based on the count
        let text = `${count} ${label}`;
        if (count > 1) {
          // Append 's' to the text for plural case
          text = text.replace(" Issue", " Issues");
        }

        badge.textContent = text;

        // Append the badge to the report badge container based on the order
        const existingBadges = reportBadge.querySelectorAll(".badge");
        const insertBeforeBadge = Array.from(existingBadges).find((b) => {
          const badgeOrder = categoryData.find(
            (c) => c.name === b.classList[1]
          )?.order;
          return badgeOrder > category.order;
        });

        if (insertBeforeBadge) {
          reportBadge.insertBefore(badge, insertBeforeBadge);
        } else {
          reportBadge.appendChild(badge);
        }
      }
    }
  }

  updateUIElement(container, severity, message, length, category) {
    const infoText = container.querySelector("[info-message]");
    const icon = container.querySelector("[info-icon]");
    const box = container.querySelector("[info-box]");
    const infoLength = container.querySelector("[info-length]");
    icon.className = "";

    const iconClasses = {
      success: "an an-chack seo-icon seo-icon-success",
      warning: "an an-square seo-icon seo-icon-warning",
      danger: "an an-triangle seo-icon seo-icon-danger",
      info: "an an-circle seo-icon seo-icon-info",
    };
    icon.className = iconClasses[severity];
    if (box) {
      box.className = "";
      box.style.display = length ? "block" : "none";
      infoLength.innerHTML = `${length || 0} Characters`;
      box.classList.add("alert", `alert-${severity}`);
    }
    infoText.innerHTML = message;

    this.updateCategoryCounts(category, severity);
  }

  updateCategoryCounts(category, severity) {
    this.categories[category] = this.categories[category] || {};
    this.categories[category][severity] =
      (this.categories[category][severity] || 0) + 1;
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

  checkTitle(cat) {
    const container = document.querySelector("[csi-title]");
    const title = this.data?.title || "";
    const length = title.length;
    const severity = this.checkLength(length, 45, 60, 25, 45);

    if (!title) {
      this.updateUIElement(
        container,
        "danger",
        "This <strong>webpage lacks a title tag!</strong> Including a title tag is crucial as it provides a concise overview of the page for search engines.",
        null
      );
    } else {
      let message = `The webpage is using a title tag with a length of <strong>${length} characters.</strong>`;

      if (severity === "warning") {
        message += ` We recommend using a title with a length between <strong>45 - 60 characters</strong> in order to fit Google Search results that have a 600-pixel limit.`;
      } else if (severity === "danger") {
        message += ` It is recommended to use well-written and engaging titles with a length between <strong>45 - 60 characters</strong>.`;
      }

      this.updateUIElement(container, severity, message, length, cat);
    }
  }

  checkDescription(cat) {
    const container = document.querySelector("[csi-description]");
    const description = this.data?.description || "";
    const length = description.length;
    const severity = this.checkLength(length, 120, 160, 80, 120);
    if (!description) {
      this.updateUIElement(
        container,
        "danger",
        "This webpage is <strong>missing a meta description tag</strong>! It is important to include this tag in order to provide a brief summary of the page for search engines. A well-written and enticing meta description can also increase the number of clicks on your site in search engine results.",
        null,
        cat
      );
    } else {
      let message = `The webpage has a meta description tag with a length of <strong>${length} characters.</strong>`;

      if (severity === "warning") {
        message += ` It is recommended to use well-written and engaging meta descriptions with a length between <strong>80 and 160 characters (including spaces).</strong>`;
      } else if (severity === "danger") {
        message += ` It is recommended to use well-written and engaging meta descriptions with a length between <strong>80 and 160 characters (including spaces)</strong>.`;
      }

      this.updateUIElement(container, severity, message, length, cat);
    }
  }

  checkHeadings(cat) {
    const container = document.querySelector("[csi-headings]");
    const headings = this.data?.headings || {};
    const h1Count = headings.h1?.length || 0;
    const h2Count = headings.h2?.length || 0;
    const h3Count = headings.h3?.length || 0;
    let severity = "success";
    let message =
      "All heading tags are <strong>well-structured and properly used</strong>.";

    if (h1Count > 1) {
      message =
        "<strong>Multiple h1 tags found</strong> on the webpage. It is recommended to have <strong>only one h1 tag</strong> for proper heading structure.";
      severity = "danger";
    } else if (h1Count === 0) {
      message =
        "<strong>No h1 tag found on the webpage</strong>. It is recommended to <strong>include an h1 tag</strong> to provide a clear and descriptive heading for search engines.";
      severity = "danger";
    } else if (h2Count === 0) {
      message =
        "<strong>No h2 tag found on the webpage</strong>. It is recommended to <strong>include an h2 tag</strong> for better organization and hierarchical structure.";
      severity = "warning";
    } else if (h3Count === 0) {
      message =
        "<strong>No h3 tag found on the webpage</strong>. Consider using h3 tags to provide subheadings and further organize your content.";
      severity = "info";
    }

    this.updateUIElement(container, severity, message, null, cat);
  }

  checkCustom404Page(cat) {
    const container = document.querySelector("[csi-404-page]");
    const custom404Page = this.data?.hasCustom404Page || false;

    const severity = custom404Page ? "success" : "danger";
    const message = custom404Page
      ? "This website <strong>has a custom 404 error page</strong>. It is recommended to have a custom 404 error page to improve the user experience for your website by letting users know that only a specific page is missing or broken (not the entire site). You can also provide helpful links, the opportunity to report bugs, and potentially track the source of broken links."
      : "This website <strong>does not have a custom 404 error page</strong>. It is recommended to create a custom 404 error page to improve the user experience and provide helpful information to visitors when they encounter broken or missing pages.";

    this.updateUIElement(container, severity, message, null, cat);
  }

  checkImageAltText(cat) {
    const container = document.querySelector("[csi-image-alt-text]");
    const imagesWithoutAltText = this.data?.imagesWithoutAltText || [];
    const totalImageCount = this.data?.totalImageCount || 0;
    const imagesWithoutAltTextCount = imagesWithoutAltText.length;

    let severity = "success";
    let message = `All images on the webpage <strong>have proper "alt" text</strong>, enhancing both accessibility and SEO optimization.`;

    if (imagesWithoutAltTextCount > 0) {
      if (imagesWithoutAltTextCount < 2) {
        message = `This webpage is using ${totalImageCount} images and <strong>${imagesWithoutAltTextCount} tag is empty or missing "alt" attribute!</strong>. It is recommended to provide descriptive "alt" text for better accessibility and SEO optimization.`;
        severity = "low";
      } else if (imagesWithoutAltTextCount < 5) {
        message = `This webpage is using ${totalImageCount} images and <strong>${imagesWithoutAltTextCount} tags are empty or missing "alt" attribute!</strong>. It is important to add descriptive "alt" text to improve accessibility and SEO optimization.`;
        severity = "warning";
      } else {
        message = `This webpage is using ${totalImageCount} "img" tags with <strong>${imagesWithoutAltTextCount} tags are empty or missing "alt" attribute!</strong>. Adding descriptive "alt" text to these images is crucial for accessibility and SEO optimization.`;
        severity = "danger";
      }
    }

    this.updateUIElement(container, severity, message, null, cat);
  }

  checkLanguage(cat) {
    const container = document.querySelector("[csi-language]");
    const language = this.data?.language || "";

    const severity = language ? "success" : "warning";
    const message = language
      ? `The <strong>lang</strong> attribute is present in the HTML tag with a value of "${language}".`
      : `This webpage has not language declared. It is recommended to include the <code>lang</code> attribute to specify the language of the webpage.`;

    this.updateUIElement(container, severity, message, null, cat);
  }

  checkFavicon(cat) {
    const container = document.querySelector("[csi-favicon]");
    const favicon = this.data?.favicon || "";

    const severity = favicon ? "success" : "warning";
    const message = favicon
      ? "This website appears to have a <strong>Favicon</strong>."
      : "This webpage is missing a <strong>favicon</strong>. It is recommended to include a favicon, which is a small icon that represents your website and appears in browser tabs and bookmarks. Having a favicon enhances the visual identity of your website.";

    this.updateUIElement(container, severity, message, null, cat);
  }

  checkRobotsTxt(cat) {
    const container = document.querySelector("[csi-robots-txt]");
    const hasRobotsTxt = this.data?.hasRobotsTxt || false;

    const severity = hasRobotsTxt ? "success" : "danger";
    const message = hasRobotsTxt
      ? 'This website has a "<strong>robots.txt</strong>" file.'
      : 'This website is <strong>missing a "robots.txt"</strong> file. This file can protect private content from appearing online, save bandwidth, and lower load time on your server. Creating and properly configuring a robots.txt file can help control the visibility of your website\'s content in search engines.';

    this.updateUIElement(container, severity, message, null, cat);
  }

  checkNoFollowTag(cat) {
    const container = document.querySelector("[csi-nofollow]");
    const hasNoFollow = this.data?.hasNoFollow || false;

    const severity = hasNoFollow ? "warning" : "success";
    const message = hasNoFollow
      ? "This website has a <strong>Nofollow Tag</strong>. Having a Nofollow tag can impact your search engine ranking"
      : "This webpage is not using the nofollow meta tag!";

    this.updateUIElement(container, severity, message, null, cat);
  }

  checkNoIndexTag(cat) {
    const container = document.querySelector("[csi-noindex]");
    const hasNoIndex = this.data?.hasNoIndex || false;

    const severity = hasNoIndex ? "danger" : "success";
    const message = hasNoIndex
      ? "This webpage includes a noindex meta tag. The presence of a noindex meta tag on this webpage instructs search engines not to index its content. As a result, the webpage will not appear in search engine results and will not be accessible through organic search queries."
      : "This webpage does not use the noindex meta tag. This means that it can be indexed by search engines.";

    this.updateUIElement(container, severity, message, null, cat);
  }

  checkSPFRecord(cat) {
    const container = document.querySelector("[csi-spf-record]");
    const spfRecord = this.data?.spfRecord || false;

    const severity = spfRecord ? "success" : "warning";
    const message = spfRecord
      ? "This DNS server is using an SPF record."
      : "This DNS server does not have an SPF record. SPF records are used to prevent email spoofing and help ensure that email messages sent from a particular domain are authorized and not forged";

    this.updateUIElement(container, severity, message, null, cat);
  }

  checkURLRedirect(cat) {
    const container = document.querySelector("[csi-url-redirect]");
    const redirects = this.data?.redirects;
    const hasRedirect = redirects !== undefined && redirects !== false;
    let severity = hasRedirect ? "warning" : "success";
    let message = hasRedirect
      ? "This URL performed redirects! While redirects are typically not advisable (as they can affect search engine indexing issues and adversely affect site loading time), one redirect may be acceptable, particularly if the URL is redirecting from a non-www version to its www version, or vice-versa."
      : "This URL doesn't have any redirects (which could potentially cause site indexation issues and site loading delays).";

    if (redirects === this.data?.url || redirects === false) {
      message = "No URL redirection was detected on the webpage.";
      severity = "success";
    }

    this.updateUIElement(container, severity, message, null, cat);
  }

  nonSEOFriendlyURLCheck(cat) {
    const container = document.querySelector("[csi-seo-friendly-url]");
    const nonSEOFriendlyLinks = this.data?.nonSEOFriendlyLinks || [];
    const linkCount = nonSEOFriendlyLinks.length;
    let severity = "success";
    let message = "All links from this webpage are SEO friendly.";
    if (
      nonSEOFriendlyLinks === undefined ||
      nonSEOFriendlyLinks === false ||
      linkCount === 0
    ) {
      this.updateUIElement(container, severity, message, null, cat);
      return;
    }
    if (linkCount < 5) {
      severity = "info";
      message = "This page includes some non-SEO friendly links.";
    } else if (linkCount >= 5 && linkCount < 10) {
      severity = "warning";
      message = "This page includes several non-SEO friendly links.";
    } else if (linkCount >= 10) {
      severity = "danger";
      message = "This page includes many non-SEO friendly links.";
    }

    this.updateUIElement(container, severity, message, null, cat);
  }
  // Permorfance check method

  checkDOMSize(cat) {
    const container = document.querySelector("[pi-dom-size]");
    const domSize = this.data?.domSize || 0;
    let severity, message;

    if (domSize < 1500) {
      severity = "success";
      message = `The Document Object Model (DOM) of this webpage has <strong>${domSize} nodes</strong> which is less than the recommended value of 1500 nodes.`;
    } else if (domSize < 1800) {
      severity = "info";
      message = `The Document Object Model (DOM) of this webpage has <strong>${domSize} nodes</strong> which is more than the recommended value of 1500 nodes. A large DOM size negatively affects site performance and increases the page load time.`;
    } else if (domSize < 2500) {
      severity = "warning";
      message = `The Document Object Model (DOM) of this webpage has <strong>${domSize} nodes</strong> which is more than the recommended value of 1500 nodes. A large DOM size negatively affects site performance and increases the page load time.`;
    } else {
      severity = "danger";
      message = `The Document Object Model (DOM) of this webpage has <strong>${domSize} nodes</strong> which exceeds the recommended value of 1500 nodes. A large DOM size negatively affects site performance and increases the page load time.`;
    }

    this.updateUIElement(container, severity, message, null, cat);
  }
  checkLoadTime(cat) {
    const container = document.querySelector("[pi-load-time]");
    const loadTime = this.data?.loadTime || 0;

    let severity, message;

    if (loadTime > 2.5) {
      severity = "danger";
      message = `The loading speed of this webpage, as measured, is approximately <strong>${loadTime} seconds</strong>, which is slower than the average loading time of <strong>2.5 seconds.</strong> It's recommended to optimize the page load time to improve user experience and overall performance.`;
    } else if (loadTime >= 1.5 && loadTime <= 2.4) {
      severity = "info";
      message = `The loading speed of this webpage, as measured, is approximately <strong>${loadTime} seconds</strong>, which is faster than the average loading time of <strong>2.5 seconds.</strong> However, there is still room for improvement. Consider optimizing the page load time further for better user experience.`;
    } else if (loadTime > 0) {
      severity = "success";
      message = `The loading speed of this webpage, as measured, is approximately <strong>${loadTime} seconds</strong>, which is faster than the average loading time of <strong>2.5 seconds.</strong> Great job! However, it's always beneficial to continue optimizing the page load time for better user experience.`;
    } else {
      severity = "info";
      message = `The loading speed of this webpage could not be determined. Please ensure accurate measurement and monitoring of the page load time to identify areas for optimization and improve overall performance.`;
    }

    this.updateUIElement(container, severity, message, null, cat);
  }

  checkPageSize(cat) {
    const container = document.querySelector("[pi-page-size]");
    const pageSizeBytes = this.data?.pageSize || 0;
    const pageSize = formatBytes(pageSizeBytes);
    const averageSize = 98.84; // Average page size in kB

    let severity, message;

    if (pageSizeBytes > averageSize * 1024 && pageSizeBytes < 120 * 1024) {
      severity = "info";
      message = `This webpage's HTML is larger than the average, measuring at <strong>${pageSize}</strong> as compared to the <strong>standard ${averageSize} kB.</strong> While it's larger than average, it is still within an acceptable range. To optimize the page size further and improve loading speeds, consider techniques such as HTML compression, utilizing CSS layouts, using external style sheets, and moving JavaScript to separate files.`;
    } else if (
      pageSizeBytes > averageSize * 1024 &&
      pageSizeBytes < 150 * 1024
    ) {
      severity = "warning";
      message = `This webpage's HTML is larger than the average, measuring at <strong>${pageSize}</strong> as compared to the <strong>standard ${averageSize} kB.</strong> It's recommended to optimize the page size to improve loading speeds and provide a better user experience. Consider techniques such as HTML compression, utilizing CSS layouts, using external style sheets, and moving JavaScript to separate files.`;
    } else if (pageSizeBytes > averageSize * 1024) {
      severity = "danger";
      message = `This webpage's HTML is larger than the average, measuring at <strong>${pageSize}</strong> as compared to the <strong>standard ${averageSize} kB.</strong> This can result in slower loading speeds, potentially causing visitors to leave and resulting in decreased revenue. To decrease the HTML size, techniques such as HTML compression, utilizing CSS layouts, using external style sheets, and moving JavaScript to separate files are effective methods.`;
    } else {
      severity = "success";
      message = `This webpage's HTML size is within the average range, measuring at <strong>${pageSize}</strong>. Great job! However, it's always beneficial to optimize the page size to improve loading speeds and provide a better user experience.`;
    }

    this.updateUIElement(container, severity, message, null, cat);
  }

  checkHttpRequests(cat) {
    const container = document.querySelector("[pi-http-requests]");
    const totalRequests = this.data?.httpRequests?.totalRequests || 0;
    const averageRequests = 25; // Average number of HTTP requests

    let severity, message;

    if (totalRequests > averageRequests && totalRequests <= 50) {
      severity = "info";
      message = `This webpage has <strong>${totalRequests} HTTP requests</strong>, which is higher than the average of ${averageRequests}. While it's more than average, it is still within an acceptable range. To optimize the page loading speed, consider reducing the number of requests by combining or minifying resources and using caching techniques.`;
    } else if (totalRequests > averageRequests && totalRequests <= 75) {
      severity = "warning";
      message = `This webpage has <strong>${totalRequests} HTTP requests</strong>, which is higher than the average of ${averageRequests}. It's recommended to optimize the page loading speed by reducing the number of requests. Consider combining or minifying resources, using caching techniques, and optimizing the loading of external scripts and stylesheets.`;
    } else if (totalRequests > averageRequests) {
      severity = "danger";
      message = `This webpage has <strong>${totalRequests} HTTP requests</strong>, which is higher than the average of ${averageRequests}. This can significantly impact the page loading speed and user experience. To improve performance, it's crucial to reduce the number of requests by combining or minifying resources, optimizing caching, and minimizing the use of external scripts and stylesheets.`;
    } else {
      severity = "success";
      message = `This webpage has <strong>${totalRequests} HTTP requests</strong>, which is within the average range of ${averageRequests}. Great job! However, optimizing the page loading speed by reducing unnecessary requests is always beneficial.`;
    }

    this.updateUIElement(container, severity, message, null, cat);
  }
}
