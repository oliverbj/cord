'''
Create a python script that does the following:
1. Read data from an excel file
2. Make a new CSV file with the following columns/rows from the excel file [X] replace to [Y]
["CW Org. Number", "Document No.", "Document Date", "Due Date", "Currency Code", "Original Amount", "Amount (LCY)"] to
["Account", "Reference", "InvoiceDate", "DueDate", "Currency", "ForeignAmount", "LocalAmount"]
3. Add two columns at the end: ["Branch", "Department"]
4. If there is missing value in the "Currency" column, fill it with "DKK"
5. Write the data to a new excel file
'''

import pandas as pd

def csv_file():
    # Read data from an excel file
    df = pd.read_excel("Copy of DK07_customer_openinvoices (002).xlsx")
    # Make a new CSV file with the following columns/rows from the excel file [X] replace to [Y]
    df = df[["CW Org. Number", "Document No.", "Document Date", "Due Date", "Currency Code", "Original Amount", "Amount (LCY)"]]
    # Add two columns at the end: ["Branch", "Department"]
    df["Branch"] = "CPH"
    df["Department"] = "BRN"
    # If there is missing value in the "Currency" column, fill it with "DKK"
    df["Currency Code"] = df["Currency Code"].fillna("DKK")

    # Format the date columns: "Document Date", "Due Date" from "DD/MM/YYYY" to "YYYYMMDD"
    df["Document Date"] = pd.to_datetime(df["Document Date"], format="%d/%m/%Y").dt.strftime("%Y%m%d")
    df["Due Date"] = pd.to_datetime(df["Due Date"], format="%d/%m/%Y").dt.strftime("%Y%m%d")

    # Rename the columns
    df.rename(columns={"CW Org. Number": "Account", "Document No.": "Reference", "Document Date": "InvoiceDate", "Due Date": "DueDate", "Currency Code": "Currency", "Original Amount": "ForeignAmount", "Amount (LCY)": "LocalAmount"}, inplace=True)

    # Write the data to a new comma seperated CSV file:
    df.to_csv("DK07_customer_openinvoices.csv", index=False)

def main():
    csv_file()

if __name__ == "__main__":
    main()
