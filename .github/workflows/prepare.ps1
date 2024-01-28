if ($env:_BUILD_BRANCH -eq "refs/heads/2.0")
{
  $env:_IS_BUILD_CI = "true"
  $env:_RELEASE_VERSION = "latest"
}
elseif ($env:_BUILD_BRANCH -like "refs/tags/*")
{
  $env:_RELEASE_VERSION = $env:_BUILD_VERSION.Substring(0,$env:_BUILD_VERSION.LastIndexOf('.'))
}

Write-Output "--------------------------------------------------"
Write-Output "CI: $env:_IS_BUILD_CI"
Write-Output "RELEASE NAME: $env:_RELEASE_NAME"
Write-Output "RELEASE VERSION: $env:_RELEASE_VERSION"
Write-Output "--------------------------------------------------"

Write-Output "_RELEASE_VERSION=${env:_RELEASE_VERSION}" >> ${env:GITHUB_ENV}
