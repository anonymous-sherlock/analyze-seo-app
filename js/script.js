class Toast {
  Show(text, color, sec) {
    const toastContainer = document.querySelector(".toast-container");
    if (toastContainer) {
      const toast = document.createElement("div");
      toast.className = `toast toast-${color} show`;
      toast.setAttribute("role", "alert");
      toast.setAttribute("aria-live", "assertive");
      toast.setAttribute("aria-atomic", "true");

      toast.innerHTML = `
          <div class="d-flex">
            <div class="toast-body">
              <span>${text}</span>
              </div>
            </div>
            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
          </div>
        `;

      toastContainer.appendChild(toast);
      setTimeout(() => {
        toast.remove();
      }, Number(sec) * 1000);
    }
  }
}
function showScreenshot(event) {
  event.preventDefault(); // Prevent form submission

  const url = document.getElementById("urlInput").value;
  const webCapture = document.getElementById("screen-shot");
  const reportDate = document.getElementById("generated-report-date");
  reportDate.innerHTML = generateReportDate();
  const resultContainer = document.querySelector(".tool-results-wrapper");
  const loader = document.querySelector(".loader-popup"); // Select the loader element

  // Hide the resultContainer and show the loader
  resultContainer.classList.remove("hidden");
  resultContainer.classList.add("show");
  document.body.style.overflowY = "hidden";
  loader.classList.remove("hidden");

  // Remove previously appended webCapture image
  webCapture.innerHTML = "";
  // Get the dimensions of the image based on the viewport
  const imgElement = document.querySelector(".mac-view");
  const { height: imgHeight, width: imgWidth } =
    imgElement.getBoundingClientRect();

  // Create and append the image element
  const img = new Image();
  img.classList.add("w-100");
  img.onload = async () => {
    await performSEOAnalysis(url);
    document.body.style.overflowY = "";
    loader.classList.add("hidden");
  };
  img.src = `https://api.screenshotmachine.com/?key=f7ee5e&url=${encodeURIComponent(
    url
  )}&dimension=${imgWidth * 3.5}x${imgHeight * 3.5}`;
  webCapture.appendChild(img);
}
class FillData {
  constructor(analysisResult) {
    this.data = analysisResult;
  }

  render() {
    const {
      url,
      title,
      description,
      pageSize,
      language,
      favicon,
      loadTime,
      spfRecord,
      httpRequests,
      headings,
      imagesWithoutAltText,
      nonSEOFriendlyLinks,
      hasRobotsTxt,
      hasNoIndex,
    } = this.data;
    const inPageLinks = {
      "Internal Links": this.data.internalLinks,
      "External Links": this.data.externalLinks,
    };

    this.updateUI(".site-url", url, "link");
    this.updateUI(".site-url-alt", url);
    this.updateUI(".site-title", title);
    this.updateUI(".site-description", description);
    this.updateUI(".page-size", pageSize, formatBytes);
    this.updateUI(".page-loadtime", loadTime, "loadTime");
    this.updateUI(".site-lang", language);
    this.updateUI(".site-icon", favicon);
    this.updateUI(".no-index-value", hasNoIndex);
    this.updateUI(".spf-record", spfRecord);
    this.updateUI(
      ".http-request-count",
      httpRequests.totalRequests,
      "httpRequests"
    );
    // render ui comoponent
    this.renderHeadings(headings);
    this.renderFavicon(favicon);
    this.renderHttpRequest(this.data);
    this.renderImageWithoutAlt(imagesWithoutAltText);
    this.renderInPageLink(inPageLinks);
    this.renderNonSEOFriendlyLinks(nonSEOFriendlyLinks);
    this.renderUrlRedirects(this.data);
  }

  updateUI(selector, message, formatter) {
    const elements = document.querySelectorAll(selector);
    if (elements.length > 0) {
      elements.forEach((element) => {
        if (typeof message === "string") {
          if (formatter === "link" && element.tagName === "A") {
            element.innerHTML = message;
            element.href = message;
          } else {
            element.innerHTML = message;
          }
        } else if (typeof formatter === "function") {
          element.innerHTML = formatter(message);
        } else if (formatter === "loadTime") {
          element.innerHTML = message + " Seconds";
        } else if (formatter === "httpRequests") {
          element.innerHTML = message + " Resources";
        } else {
          element.innerHTML = ""; // Set a default value or leave it empty
        }
      });
    }
  }

  renderFavicon(favicon) {
    const siteIcon = document.querySelector(".site-icon");
    let siteIconImg = document.querySelector(".site-icon-img");
    let faviconPreview = document.querySelector("[favicon-preview]");

    if (favicon) {
      if (!siteIconImg) {
        siteIconImg = document.createElement("img");
        siteIconImg.classList.add("site-icon-img");
        siteIconImg.style.width = "16px";
        faviconPreview.prepend(siteIconImg);
      }
      siteIconImg.setAttribute("src", favicon);
    } else {
      if (siteIconImg) {
        siteIconImg.remove();
      }
    }

    siteIcon.innerHTML = favicon;
  }
  renderHeadings(data) {
    const headingContainer = document.getElementById("heading-container");
    headingContainer.innerHTML = "";
    for (const heading in data) {
      const headingData = data[heading];
      // Skip appending if data is empty or null
      if (!headingData || headingData.length === 0) {
        continue;
      }
      const headingTag = document.createElement("li");
      headingTag.classList.add("list-group-item");

      const headingDiv = document.createElement("div");
      headingDiv.classList.add("d-flex", "justify-content-between");
      headingDiv.setAttribute("data-bs-toggle", "collapse");
      headingDiv.setAttribute("href", `#multiCollapseH_${heading}`);
      headingDiv.setAttribute("role", "button");
      headingDiv.setAttribute("aria-expanded", "false");
      headingDiv.setAttribute("aria-controls", `multiCollapseH_${heading}`);

      const headingTitle = document.createElement("p");
      headingTitle.classList.add("mb-0", "text-uppercase");
      headingTitle.textContent = heading;

      const headingCount = document.createElement("span");
      headingCount.classList.add("badge", "badge-primary");
      headingCount.textContent = headingData.length.toString();

      headingDiv.appendChild(headingTitle);
      headingDiv.appendChild(headingCount);

      const collapseDiv = document.createElement("div");
      collapseDiv.classList.add("collapse");
      collapseDiv.setAttribute("id", `multiCollapseH_${heading}`);

      const hrElement = document.createElement("hr");

      const olElement = document.createElement("ol");
      olElement.classList.add("mb-0", "pb-2");

      for (const item of headingData) {
        const liElement = document.createElement("li");
        liElement.classList.add("py-1", "text-break", "heading-data");
        liElement.textContent = item;
        olElement.appendChild(liElement);
      }

      collapseDiv.appendChild(hrElement);
      collapseDiv.appendChild(olElement);

      headingTag.appendChild(headingDiv);
      headingTag.appendChild(collapseDiv);

      headingContainer.appendChild(headingTag);
    }
  }

  renderImageWithoutAlt(imagesWithoutAltText) {
    const imageList = document.querySelector("[img-alt-missing-list]");
    // Clear the existing content
    imageList.innerHTML = "";

    if (
      !Array.isArray(imagesWithoutAltText) ||
      imagesWithoutAltText.length === 0
    ) {
      return;
    }
    const ulElement = document.createElement("ul");
    ulElement.classList.add("list-group", "rounded-0", "p-0", "mt-3");
    ulElement.setAttribute("img-alt-missing-list", "");

    const liElement = document.createElement("li");
    liElement.classList.add("list-group-item");

    const divElement = document.createElement("div");
    divElement.classList.add("d-flex", "justify-content-between");
    divElement.setAttribute("data-bs-toggle", "collapse");
    divElement.setAttribute("href", "#imagesWithoutAltAttr");
    divElement.setAttribute("role", "button");
    divElement.setAttribute("aria-expanded", "false");
    divElement.setAttribute("aria-controls", "imagesWithoutAltAttr");
    divElement.setAttribute("bis_skin_checked", "1");

    const pElement = document.createElement("p");
    pElement.classList.add("mb-0");
    pElement.textContent = "Images without ALT tag";

    const spanElement = document.createElement("span");
    spanElement.classList.add(
      "badge",
      "badge-primary",
      "missing-alt-image-count"
    );
    spanElement.textContent = imagesWithoutAltText.length.toString();

    divElement.appendChild(pElement);
    divElement.appendChild(spanElement);

    const collapseDivElement = document.createElement("div");
    collapseDivElement.classList.add("collapse");
    collapseDivElement.setAttribute("id", "imagesWithoutAltAttr");
    collapseDivElement.setAttribute("bis_skin_checked", "1");

    const hrElement = document.createElement("hr");

    const olElement = document.createElement("ol");
    olElement.classList.add("mb-0", "pb-2");

    imagesWithoutAltText.forEach((image) => {
      const liItem = document.createElement("li");
      liItem.classList.add("py-1", "text-break");

      const aElement = document.createElement("a");
      aElement.href = image;
      aElement.target = "_blank";
      aElement.rel = "noopener noreferrer";
      aElement.textContent = image;

      liItem.appendChild(aElement);
      olElement.appendChild(liItem);
    });

    collapseDivElement.appendChild(hrElement);
    collapseDivElement.appendChild(olElement);

    liElement.appendChild(divElement);
    liElement.appendChild(collapseDivElement);

    ulElement.appendChild(liElement);

    imageList.appendChild(ulElement);
    imageList.style.display = "block";
  }
  renderHttpRequest(data) {
    const { Resources, totalRequests } = data?.httpRequests;
    const httpRequestContainer = document.getElementById(
      "http-request-container"
    );

    const httpRequestCount = [
      ...document.getElementsByClassName("http-request-count"),
    ];

    httpRequestCount.forEach((item) => {
      item.innerHTML = `${totalRequests} Resources`;
    });
    httpRequestContainer.innerHTML = "";

    // Iterate over the resources and create <li> elements for each one
    for (const resource in Resources) {
      const resourceData = Resources[resource];

      // Skip rendering if the resourceData array is empty
      if (resourceData.length === 0) {
        continue;
      }
      // Create the <li> element for the resource
      const li = document.createElement("li");
      li.classList.add("list-group-item");

      // Create the first <div> with class "d-flex justify-content-between"
      const div1 = document.createElement("div");
      div1.classList.add("d-flex", "justify-content-between");
      div1.setAttribute("data-bs-toggle", "collapse");
      div1.setAttribute("href", `#multiCollapseH_links_${resource}`);
      div1.setAttribute("role", "button");
      div1.setAttribute("aria-expanded", "false");
      div1.setAttribute("aria-controls", `multiCollapseH_links_${resource}`);
      div1.setAttribute("bis_skin_checked", "1");

      // Create the <p> element inside the first <div>
      const p = document.createElement("p");
      p.classList.add("mb-0");
      p.textContent = resource;
      div1.appendChild(p);

      // Create the <span> element with class "badge badge-primary"
      const span = document.createElement("span");
      span.classList.add("badge", "badge-primary");
      span.textContent = resourceData?.length;
      div1.appendChild(span);

      li.appendChild(div1);

      // Create the second <div> with class "collapse"
      const div2 = document.createElement("div");
      div2.classList.add("collapse");
      div2.setAttribute("id", `multiCollapseH_links_${resource}`);
      div2.setAttribute("bis_skin_checked", "1");

      // Create the <hr> element inside the second <div>
      const hr = document.createElement("hr");
      div2.appendChild(hr);

      // Create the <ol> element inside the second <div>
      const ol = document.createElement("ol");
      ol.classList.add("mb-0", "pb-2");

      // Iterate over the resourceData and create <li> elements for each item
      for (const item of resourceData) {
        const liItem = document.createElement("li");
        liItem.classList.add("py-1", "text-break");
        liItem.textContent = item;
        ol.appendChild(liItem);
      }

      div2.appendChild(ol);
      li.appendChild(div2);

      // Append the <li> element to the httpRequestContainer
      httpRequestContainer.appendChild(li);
    }
  }
  renderInPageLink(data) {
    const linkContainer = document.getElementById("link-container");
    linkContainer.innerHTML = "";

    for (const link in data) {
      const linkData = data[link];
      // Skip appending if data is empty or null
      if (!linkData || linkData.length === 0) {
        continue;
      }
      const linkTag = document.createElement("li");
      linkTag.classList.add("list-group-item");
      const linkTab = link.toLowerCase().replaceAll(" ", "");
      const linkDiv = document.createElement("div");
      linkDiv.classList.add("d-flex", "justify-content-between");
      linkDiv.setAttribute("data-bs-toggle", "collapse");
      linkDiv.setAttribute("href", `#multiCollapseH_${linkTab}`);
      linkDiv.setAttribute("role", "button");
      linkDiv.setAttribute("aria-expanded", "false");
      linkDiv.setAttribute("aria-controls", `multiCollapseH_${linkTab}`);

      const linkTitle = document.createElement("p");
      linkTitle.classList.add("mb-0");
      linkTitle.textContent =
        link.charAt(0).toUpperCase() + link.slice(1).toLowerCase(); // Capitalize the first letter and lowercase the rest

      const linkCount = document.createElement("span");
      linkCount.classList.add("badge", "badge-primary");
      linkCount.textContent = linkData.length.toString();

      linkDiv.appendChild(linkTitle);
      linkDiv.appendChild(linkCount);

      const collapseDiv = document.createElement("div");
      collapseDiv.classList.add("collapse");
      collapseDiv.setAttribute("id", `multiCollapseH_${linkTab}`);

      const hrElement = document.createElement("hr");

      const olElement = document.createElement("ol");
      olElement.classList.add("mb-0", "pb-2");

      for (const item of linkData) {
        const liElement = document.createElement("li");
        liElement.classList.add("py-1", "text-break");

        const linkAnchor = document.createElement("a");
        linkAnchor.href = item.url; // Use the "url" property of the link item
        linkAnchor.textContent = item.text; // Use the "text" property of the link item

        liElement.appendChild(linkAnchor);
        olElement.appendChild(liElement);
      }

      collapseDiv.appendChild(hrElement);
      collapseDiv.appendChild(olElement);

      linkTag.appendChild(linkDiv);
      linkTag.appendChild(collapseDiv);

      linkContainer.appendChild(linkTag);
    }
  }
  renderNonSEOFriendlyLinks(links) {
    const container = document.getElementById("non-seo-friendly-url");

    // Remove the existing UI element if it exists
    const existingUIElement = document.getElementById("non-seo-friendly-ui");
    if (existingUIElement) {
      container.removeChild(existingUIElement);
    }
    if (!links || !Array.isArray(links)) {
      return;
    }

    const ulElement = document.createElement("ul");
    ulElement.classList.add("list-group", "rounded-0", "p-0", "mt-3");
    ulElement.id = "non-seo-friendly-ui";

    const liElement = document.createElement("li");
    liElement.classList.add("list-group-item");

    const divElement = document.createElement("div");
    divElement.classList.add("d-flex", "justify-content-between");
    divElement.setAttribute("data-bs-toggle", "collapse");
    divElement.setAttribute("href", "#multiCollapseH_friendly");
    divElement.setAttribute("role", "button");
    divElement.setAttribute("aria-expanded", "false");
    divElement.setAttribute("aria-controls", "multiCollapseH_friendly");
    divElement.setAttribute("bis_skin_checked", "1");

    const pElement = document.createElement("p");
    pElement.classList.add("mb-0");
    pElement.textContent = "SEO unfriendly URL";

    const spanElement = document.createElement("span");
    spanElement.classList.add("badge", "badge-primary");
    spanElement.textContent = links.length.toString();

    divElement.appendChild(pElement);
    divElement.appendChild(spanElement);

    const collapseDivElement = document.createElement("div");
    collapseDivElement.classList.add("collapse");
    collapseDivElement.setAttribute("id", "multiCollapseH_friendly");
    collapseDivElement.setAttribute("bis_skin_checked", "1");

    const hrElement = document.createElement("hr");

    const olElement = document.createElement("ol");
    olElement.classList.add("mb-0", "pb-2");

    links.forEach((link) => {
      const liElement = document.createElement("li");
      liElement.classList.add("py-1", "text-break");

      const aElement = document.createElement("a");
      aElement.href = link;
      aElement.target = "_blank";
      aElement.rel = "noopener noreferrer";
      aElement.textContent = link;

      liElement.appendChild(aElement);
      olElement.appendChild(liElement);
    });

    collapseDivElement.appendChild(hrElement);
    collapseDivElement.appendChild(olElement);

    liElement.appendChild(divElement);
    liElement.appendChild(collapseDivElement);

    ulElement.appendChild(liElement);

    container.appendChild(ulElement);
  }
  renderUrlRedirects(data) {
    const { url, redirects } = data;
    const UrlRedirectElement = document.querySelector(".url-redirect");
    if (redirects) {
      if (url !== redirects) {
        UrlRedirectElement.innerHTML = `<code>${url}</code>
      <span class="px-1 bg-info text-white">&rarr;</span>
      <code>${redirects}</code>`;
      }
    }
  }
}

async function performSEOAnalysis(url) {
  const endpoint = "api/report/seo-res.php"; // Update with the actual PHP script filename or endpoint
  const requestUrl = `${endpoint}?url=${encodeURIComponent(url)}`;

  try {
    const response = await fetch(requestUrl);
    if (response.ok) {
      const analysisResult = await response.json();
      // Process the analysis result as needed
      if (!analysisResult.error) {
        const f = new FillData(analysisResult);
        const errorTracker = new ErrorTracker(analysisResult);
        f.render();
        errorTracker.analyze();
      } else {
        const toastError = new Toast();
        const firstValue = Object.values(analysisResult)[0];
        toastError.Show(firstValue, "danger", 5);
      }
    } else {
      throw new Error(`Error: ${response.status}`);
    }
  } catch (error) {
    console.log(error);
  }
}
