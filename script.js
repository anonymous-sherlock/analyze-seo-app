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

  // Add a small delay to allow the element to be displayed before applying animation
  // setTimeout(() => {
  //   resultContainer.style.opacity = "1";
  //   resultContainer.style.transform = "translateY(0)";
  //   resultContainer.style.scrollMarginTop = "100px";
  // }, 300);
  // Remove previously appended webCapture image
  webCapture.innerHTML = "";

  // Get the dimensions of the image based on the viewport
  const imgElement = document.querySelector(".mac-view");
  const { height: imgHeight, width: imgWidth } =
    imgElement.getBoundingClientRect();

  const img = document.createElement("img");
  img.src = `https://api.screenshotmachine.com/?key=f7ee5e&url=${encodeURIComponent(
    url
  )}&dimension=${imgWidth * 3}x${imgHeight * 3}`;
  img.classList.add("w-100");
  webCapture.appendChild(img);

  // Hide the loader after the image is loaded
  img.onload = async () => {
    // Perform SEO analysis
    await performSEOAnalysis(url);
    document.body.style.overflowY = "";
    loader.classList.add("hidden");
  };
}

// report generated date
function generateReportDate() {
  const currentDate = new Date();
  const monthNames = [
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December",
  ];

  const month = monthNames[currentDate.getMonth()];
  const day = currentDate.getDate();
  const year = currentDate.getFullYear();
  let hour = currentDate.getHours();
  const minute = currentDate.getMinutes();
  const period = hour >= 12 ? "pm" : "am";

  hour %= 12;
  hour = hour || 12;

  const formattedDate = `${month} ${day}, ${year} ${hour}:${minute
    .toString()
    .padStart(2, "0")} ${period}`;

  return `Report generated on ${formattedDate}`;
}
// performing seo analysis
async function performSEOAnalysis(url) {
  // Construct the URL with the URL parameter
  const endpoint = "seo-res.php"; // Update with the actual PHP script filename or endpoint
  const requestUrl = `${endpoint}?url=${encodeURIComponent(url)}`;

  try {
    const response = await fetch(requestUrl);
    if (response.ok) {
      const analysisResult = await response.json();
      // Process the analysis result as needed
      fillData(analysisResult);
      // ErrorTracker(analysisResult);
      const errorTracker = new ErrorTracker(analysisResult);
      errorTracker.analyze();
    } else {
      throw new Error(`Error: ${response.status}`);
    }
  } catch (error) {
    // Handle errors
    console.log(error);
  }
}
// format Page Size Bytes
function formatBytes(bytes) {
  if (bytes < 1024) {
    return `${bytes} B`;
  } else if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(2)} KB`;
  } else if (bytes < 1024 * 1024 * 1024) {
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
  } else {
    return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`;
  }
}
// render headings
async function renderHeadings({ headings: data }) {
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

async function renderDescription(data) {
  const { description } = data;
  const siteDescriptionElements =
    document.getElementsByClassName("site-description");

  for (let i = 0; i < siteDescriptionElements.length; i++) {
    const siteDescriptionElement = siteDescriptionElements[i];
    siteDescriptionElement.innerHTML = description;
  }
}

async function renderTitle(data) {
  const { title } = data;
  const siteTitleElements = document.getElementsByClassName("site-title");
  for (let i = 0; i < siteTitleElements.length; i++) {
    const siteTitleElement = siteTitleElements[i];
    siteTitleElement.innerHTML = title;
  }
}
async function renderSPFRecord(data) {
  const { spfRecord } = data;
  const spfRecordElement = document.querySelector(".spf-record");
  spfRecordElement.innerHTML = spfRecord;
}
async function renderUrlRedirects(data) {
  const { url, redirects } = data;
  const UrlRedirectElement = document.querySelector(".url-redirect");
  if (url !== redirects) {
    UrlRedirectElement.innerHTML = `<code>${url}</code>
    <span class="px-1 bg-info text-white">→</span>
    <code>${redirects}</code>`;
  }
}
function renderUnsafeLink(data) {
  const unsafeLinkContainer = document.getElementById("unsafe-link-container");

  const { unsafeLinks } = data;

  const linkItems = unsafeLinks
    .map(
      (elem) => `
    <li class="py-1 text-break">
      <a href="${elem.url}" target="_blank" rel="noopener noreferrer">${elem.url}</a>
    </li>
  `
    )
    .join("");

  const html = `
    <li class="list-group-item">
      <div class="d-flex justify-content-between" data-bs-toggle="collapse" href="#coLinksCollapse" role="button" aria-expanded="false" aria-controls="coLinksCollapse">
        <p class="mb-0">Unsafe Cross-Origin Links</p>
        <span class="badge badge-primary">${unsafeLinks.length}</span>
      </div>
      <div class="collapse" id="coLinksCollapse">
        <hr>
        <ol class="mb-0 pb-2">
          ${linkItems}
        </ol>
      </div>
    </li>
  `;
  unsafeLinkContainer.innerHTML = html;
}

async function fillData(data) {
  const siteURLElements = document.getElementsByClassName("site-url");
  const siteURLAltElements = document.getElementsByClassName("site-url-alt");
  const imagesWithoutAltAttr = document.getElementById("imagesWithoutAltAttr");
  const totalImageCount = document.querySelector(".total-image-count");
  const pageSizeElements = document.querySelectorAll(".page-size");
  const siteLanguage = document.querySelector(".site-lang");
  const siteIcon = document.querySelector(".site-icon");
  const siteIconImg = document.querySelector(".site-icon-img");

  siteIcon.innerHTML = data?.favicon;
  siteIconImg.setAttribute("src", data?.favicon);
  const missingAltImageCount = document.querySelectorAll(
    ".missing-alt-image-count"
  );

  renderTitle(data);
  renderDescription(data);
  renderHeadings(data);
  renderDescription(data);
  renderSitemap(data);
  renderSPFRecord(data);
  renderUrlRedirects(data);
  renderUnsafeLink(data);
  renderHttpRequest(data);
  renderDeprecatedTag(data);

  const inPageLinks = {
    "Internal Links": data.internalLinks,
    "External Links": data.externalLinks,
  };
  renderInPageLink(inPageLinks);

  totalImageCount.innerHTML = data?.totalImageCount;
  siteLanguage.innerHTML = data?.language;

  for (let i = 0; i < pageSizeElements.length; i++) {
    const pageSize = pageSizeElements[i];
    pageSize.innerHTML = formatBytes(data?.pageSize);
  }

  for (let i = 0; i < missingAltImageCount.length; i++) {
    const missingAlt = missingAltImageCount[i];
    missingAlt.innerHTML = data?.imagesWithoutAltText.length;
  }

  for (let i = 0; i < siteURLElements.length; i++) {
    const siteURLElement = siteURLElements[i];
    siteURLElement.innerHTML = data?.url;
    siteURLElement.setAttribute("href", data?.url);
  }

  for (let i = 0; i < siteURLAltElements.length; i++) {
    const siteURLAltElement = siteURLAltElements[i];
    siteURLAltElement.innerHTML = data?.url;
  }

  let imagesWithoutAltHtml = "";
  if (data?.imagesWithoutAltText) {
    imagesWithoutAltHtml += '<ol class="mb-0 pb-2">';
    data?.imagesWithoutAltText.forEach((image) => {
      imagesWithoutAltHtml += `<li class="py-1 text-break"><a href="${image}" target="_blank" rel="noopener noreferrer">${image}</a></li>`;
    });
    imagesWithoutAltHtml += "</ol>";
  }

  imagesWithoutAltAttr.innerHTML = imagesWithoutAltHtml;
}

async function renderSitemap(data) {
  const { sitemap: sitemapURL } = data;
  const sitemap = document.querySelector("[data-sitemap]");
  const checkSitemap = document.querySelector("[check-sitemap]");

  const sitemapHTML = `<li class="list-group-item">
    <div
      class="d-flex justify-content-between"
      data-bs-toggle="collapse"
      href="#seoReport_sitemaps"
      role="button"
      aria-expanded="false"
      aria-controls="seoReport_sitemaps"
      bis_skin_checked="1"
    >
      <p class="mb-0">Sitemaps</p>
      <span class="badge badge-primary">1</span>
    </div>
    <div
      class="collapse"
      id="seoReport_sitemaps"
      bis_skin_checked="1"
    >
      <hr />
      <ol class="mb-0 pb-2">
        <li class="py-1 text-break">
          <a
            href="${sitemapURL}"
            target="_blank"
            rel="noopener noreferrer"
          >${sitemapURL}</a>
        </li>
      </ol>
    </div>
  </li>`;

  if (sitemapURL) {
    sitemap.innerHTML = sitemapHTML;
  } else {
    checkSitemap.innerHTML = "No Sitemap Found.";
  }
}

// render in page links
function renderInPageLink(data) {
  const linkContainer = document.getElementById("link-container");
  linkContainer.innerHTML = "";
  const internalLinksCount = document.querySelector(".internal-links-count");
  internalLinksCount.innerHTML = data["Internal Links"].length.toString();

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

function renderHttpRequest(data) {
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
    span.textContent = resourceData.length;
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

async function renderDeprecatedTag(data) {
  const { deprecatedTags } = data;
  const deprecatedTagsWrapper = document.getElementById("deprecated-tag");
  deprecatedTagsWrapper.innerHTML = "";

  if (Object.keys(deprecatedTags).length === 0) {
    return; // Exit the function if deprecatedTags is empty
  }

  const deprecatedTagsContainer = document.createElement("div");
  deprecatedTagsContainer.classList.add(
    "mt-3",
    "border",
    "rounded",
    "px-3",
    "py-2"
  );
  deprecatedTagsContainer.setAttribute("bis_skin_checked", "1");

  let isFirstTag = true; // Track the first tag

  for (const tagName in deprecatedTags) {
    const tagCount = deprecatedTags[tagName];

    const tagContainer = document.createElement("div");
    tagContainer.classList.add("d-flex", "justify-content-between");
    tagContainer.setAttribute("bis_skin_checked", "1");

    const tagNameElement = document.createElement("div");
    tagNameElement.classList.add("tag-name");
    tagNameElement.setAttribute("bis_skin_checked", "1");
    tagNameElement.textContent = `<${tagName}>`;

    const tagCountElement = document.createElement("div");
    tagCountElement.classList.add("tag-count");
    tagCountElement.setAttribute("bis_skin_checked", "1");

    const badgeElement = document.createElement("span");
    badgeElement.classList.add("badge", "badge-primary");
    badgeElement.textContent = tagCount;

    tagCountElement.appendChild(badgeElement);

    tagContainer.appendChild(tagNameElement);
    tagContainer.appendChild(tagCountElement);

    if (!isFirstTag) {
      tagContainer.classList.add("mt-3"); // Add mt-3 class to tags after the first tag
    } else {
      isFirstTag = false; // Update isFirstTag after the first tag
    }

    deprecatedTagsContainer.appendChild(tagContainer);
  }

  deprecatedTagsWrapper.appendChild(deprecatedTagsContainer);
}
